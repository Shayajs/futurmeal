<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BrightShield — Connexion</title>
</head>
<body style="font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #0b0f14; color: #e5e9f0;">
    <p>{{ $status === 'success' ? 'Connexion réussie, retour à Futurmeal…' : ($message ?? 'Connexion refusée.') }}</p>
    <script>
        (function () {
            var payload = {
                type: 'brightshield:callback',
                status: @json($status),
                redirect: @json($redirect),
                message: @json($message),
            };

            if (window.opener && ! window.opener.closed) {
                window.opener.postMessage(payload, @json(url('/')));
                window.close();
            } else if (payload.redirect) {
                window.location.replace(payload.redirect);
            }
        })();
    </script>
</body>
</html>
