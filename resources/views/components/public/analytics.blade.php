@php($tracking = \App\Services\AnalyticsService::publicTracking(request()))
@if($tracking)
<style>
.analytics-consent[hidden]{display:none!important}.analytics-consent{position:fixed;bottom:1rem;left:1rem;right:1rem;max-width:48rem;z-index:1000;padding:1.2rem;background:#fff;border:1px solid #cbd5cf;box-shadow:0 4px 24px #0002;border-radius:.5rem;color:#18211f}.analytics-consent p{margin:.4rem 0 1rem;font-size:.95rem}.analytics-consent-actions{display:flex;gap:.6rem;flex-wrap:wrap}.analytics-consent button,.analytics-preferences{font:inherit;cursor:pointer}.analytics-consent button{padding:.65rem 1rem;border:1px solid #173b35;border-radius:.25rem;background:#fff;color:#173b35}.analytics-preferences{position:fixed;bottom:.5rem;right:.5rem;z-index:999;background:#fff;color:#173b35;border:1px solid #cbd5cf;border-radius:.25rem;padding:.35rem .6rem;font-size:.8rem}
</style>
<section class="analytics-consent" data-analytics-consent hidden aria-labelledby="analytics-consent-title" data-clarity-mask="true">
    <strong id="analytics-consent-title">Your analytics choice</strong>
    <p>With your permission, {{ isset($tracking['clarity']) ? 'Google Analytics measures readership and Microsoft Clarity records masked interactions' : 'Google Analytics measures readership' }} to help improve this website. Optional tracking stays off until you accept. You can change your choice at any time. <a href="{{ route('policies.show', 'privacy') }}">Privacy details</a></p>
    <div class="analytics-consent-actions">
        <button type="button" data-analytics-accept>Accept analytics</button>
        <button type="button" data-analytics-reject>Necessary only</button>
    </div>
</section>
<button class="analytics-preferences" type="button" data-analytics-preferences>Analytics preferences</button>
<script id="journal-analytics-config" type="application/json">{!! json_encode($tracking, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
<script>{!! file_get_contents(resource_path('js/public-analytics.js')) !!}</script>
@endif
