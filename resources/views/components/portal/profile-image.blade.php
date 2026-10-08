@props(['path' => null, 'name' => 'Profile'])
<div class="portal-field field-span" data-profile-image>
    <label for="avatar">Profile image <span>JPG, PNG or WebP · up to 5 MB</span></label>
    <div class="profile-image-control">
        <img class="profile-image-preview" data-profile-image-preview @if($path) src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($path) }}" @else hidden @endif alt="Profile image for {{ $name }}" width="96" height="96">
        <div>
            <input class="portal-input" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-profile-image-input>
            <p class="portal-help">Choose a photo, then save your profile. Your photo, name, organization and designation will appear on your public profile after email verification and account approval. Leave this empty to keep your current image.</p>
            @if(auth()->user()?->hasPublicProfile())
                <p><a href="{{ route('people.show', auth()->user()) }}" target="_blank" rel="noopener">View public profile</a></p>
            @endif
            <x-portal.field-error name="avatar" />
        </div>
    </div>
</div>
