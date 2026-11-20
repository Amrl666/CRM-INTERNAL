{!! $htmlBody !!}

@if ($bannerBytes !== '')
    <div style="margin-top: 24px; width: 100%;">
        <img
            src="{{ $message->embedData($bannerBytes, 'email-footer-banner.jpg', 'image/jpeg') }}"
            alt=""
            style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;"
        >
    </div>
@endif
