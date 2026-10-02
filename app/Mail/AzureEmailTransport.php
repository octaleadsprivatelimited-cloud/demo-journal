<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

final class AzureEmailTransport extends AbstractTransport
{
    public function __construct(private readonly string $endpoint)
    {
        parent::__construct();
    }

    public function __toString(): string { return 'azure-communication-email'; }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || ! preg_match('~^https://[a-z0-9.-]+\.communication\.azure\.com/?$~i', $this->endpoint)) {
            throw new TransportException('Azure email endpoint or message is invalid.');
        }
        // The token stays in memory. Do not log metadata responses or message bodies.
        $identity = Http::withoutRedirecting()->connectTimeout(3)->timeout(5)->withHeaders(['Metadata'=>'true'])
            ->get('http://169.254.169.254/metadata/identity/oauth2/token', [
                'api-version'=>'2018-02-01', 'resource'=>'https://communication.azure.com/',
            ]);
        if (! $identity->successful() || ! is_string($identity->json('access_token'))) {
            throw new TransportException('Azure email authentication is unavailable.');
        }
        $addresses = fn (array $items) => array_map(fn ($address) => ['address'=>$address->getAddress(), 'displayName'=>$address->getName()], $items);
        $payload = [
            'senderAddress'=>$email->getFrom()[0]->getAddress(),
            'content'=>['subject'=>$email->getSubject(), 'plainText'=>$email->getTextBody() ?? '', 'html'=>$email->getHtmlBody() ?? ''],
            'recipients'=>array_filter(['to'=>$addresses($email->getTo()), 'cc'=>$addresses($email->getCc()), 'bcc'=>$addresses($email->getBcc())]),
            'userEngagementTrackingDisabled'=>true,
        ];
        if ($email->getReplyTo()) $payload['replyTo']=$addresses($email->getReplyTo());
        foreach ($email->getAttachments() as $attachment) {
            $body=$attachment->getBody();
            $payload['attachments'][]=['name'=>$attachment->getFilename(), 'contentType'=>$attachment->getMediaType().'/'.$attachment->getMediaSubtype(), 'contentInBase64'=>base64_encode(is_resource($body) ? stream_get_contents($body) : $body)];
        }
        // Do not automatically retry a send: an uncertain response could already have queued it.
        $response = Http::withoutRedirecting()->connectTimeout(5)->timeout(20)->withToken($identity->json('access_token'))
            ->post(rtrim($this->endpoint, '/').'/emails:send?api-version=2023-03-31', $payload);
        if ($response->status() !== 202) {
            throw new TransportException('Azure email did not accept the message (HTTP '.$response->status().').');
        }
    }
}
