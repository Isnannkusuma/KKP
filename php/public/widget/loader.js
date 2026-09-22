(function () {
    'use strict';

    var currentScript = document.currentScript;
    var widgetKey = currentScript.getAttribute('data-widget-key');

    if (!widgetKey) {
        console.error('[KKP Chat Widget] data-widget-key tidak ditemukan pada tag <script>.');
        return;
    }

    // Origin tempat script ini dimuat (otomatis, tidak perlu dikonfigurasi manual)
    var scriptUrl = new URL(currentScript.src);
    var API_ORIGIN = scriptUrl.origin;

    var isOpen = false;
    var pendingIdentityToken = null;
    var iframeReady = false;

    // ---------- Bubble button ----------
    var bubble = document.createElement('button');
    bubble.setAttribute('aria-label', 'Buka asisten chat');
    bubble.innerHTML = '💬';
    bubble.style.cssText = [
        'position:fixed', 'bottom:20px', 'right:20px', 'width:56px', 'height:56px',
        'border-radius:50%', 'background:#2563eb', 'color:white', 'border:none',
        'font-size:24px', 'cursor:pointer', 'box-shadow:0 4px 14px rgba(0,0,0,0.25)',
        'z-index:999999', 'display:flex', 'align-items:center', 'justify-content:center',
    ].join(';');

    // ---------- Iframe container ----------
    var container = document.createElement('div');
    container.style.cssText = [
        'position:fixed', 'bottom:88px', 'right:20px', 'width:380px', 'max-width:92vw',
        'height:560px', 'max-height:75vh', 'border-radius:16px', 'overflow:hidden',
        'box-shadow:0 8px 30px rgba(0,0,0,0.25)', 'z-index:999999', 'display:none',
        'background:white',
    ].join(';');

    var iframe = document.createElement('iframe');
    iframe.src = API_ORIGIN + '/widget/chat.html?widget_key=' + encodeURIComponent(widgetKey);
    iframe.style.cssText = 'width:100%;height:100%;border:none;';
    iframe.setAttribute('title', 'Asisten Chat');
    container.appendChild(iframe);

    iframe.addEventListener('load', function () {
        iframeReady = true;
        sendInitToIframe();
    });

    function sendInitToIframe() {
        iframe.contentWindow.postMessage({
            type: 'kkp-chat-init',
            pageOrigin: window.location.origin,
            identityToken: pendingIdentityToken,
        }, API_ORIGIN);
    }

    function toggle() {
        isOpen = !isOpen;
        container.style.display = isOpen ? 'block' : 'none';
        bubble.innerHTML = isOpen ? '✕' : '💬';
    }

    bubble.addEventListener('click', toggle);

    document.body.appendChild(container);
    document.body.appendChild(bubble);

    // ---------- API publik untuk situs yang memasang widget ----------
    // Dipanggil dari halaman situs lain (setelah user login di situs itu):
    //   KkpChatWidget.setIdentity('TOKEN_YANG_DIBUAT_BACKEND_ANDA');
    window.KkpChatWidget = {
        setIdentity: function (identityToken) {
            pendingIdentityToken = identityToken;
            if (iframeReady) {
                sendInitToIframe();
            }
        },
        open: function () {
            if (!isOpen) toggle();
        },
        close: function () {
            if (isOpen) toggle();
        },
    };
})();