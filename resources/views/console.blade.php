<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $instance->order->packPrice->pack->name ?? 'VPS' }} Console</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vmware-wmks@1.0.0/css/css/wmks-all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui@1.13.3/dist/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vmware-wmks@1.0.0/wmks.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; background: #000; overflow: hidden; }
        #wmks-wrapper { width: 100vw; height: 100vh; }
        #overlay {
            position: fixed; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: #111; color: #ccc; font-family: sans-serif; font-size: 0.9rem;
            gap: 0.75rem; z-index: 10;
        }
        #overlay.hidden { display: none; }
        #overlay a { color: #6ea8fe; text-decoration: none; font-size: 0.8rem; }
    </style>
</head>
<body>
    <div id="overlay">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#6ea8fe" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="20" height="8" x="2" y="2" rx="2"/><rect width="20" height="8" x="2" y="14" rx="2"/>
            <line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/>
        </svg>
        <span id="overlay-msg">Connecting to console&hellip;</span>
        <a href="javascript:history.back()">← Close</a>
    </div>

    <div id="wmks-wrapper"></div>

    <script>
        var wmks = WMKS.createWMKS('wmks-wrapper', {
            rescale: 'stretch',
            changeResolution: true,
            useVNCHandshake: false,
        });

        wmks.register('connectionstatechange', function (e, data) {
            var state   = data && (typeof data === 'string' ? data : data.state);
            var overlay = document.getElementById('overlay');
            var msg     = document.getElementById('overlay-msg');

            if (state === 'connected') {
                overlay.classList.add('hidden');
            } else if (state === 'disconnected') {
                overlay.classList.remove('hidden');
                msg.textContent = 'Console disconnected. Close this tab and click Console again.';
            } else {
                overlay.classList.remove('hidden');
                msg.textContent = 'Connecting to console…';
            }
        });

        wmks.connect('{{ $consoleUrl }}');
    </script>
</body>
</html>
