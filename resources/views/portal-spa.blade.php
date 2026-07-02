{{--
    Shell HTML de la SPA portail (C12.1, BRIEF §6, ADR-0004).
    Miroir de portal-spa/index.html : les assets hashés sont injectés depuis
    le manifest Vite par PortalSpaController ($js, $css, $preloads).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Ecoworking</title>
    @foreach ($css as $href)
        <link rel="stylesheet" href="{{ $href }}">
    @endforeach
    @foreach ($preloads as $href)
        <link rel="modulepreload" href="{{ $href }}">
    @endforeach
    <script type="module" src="{{ $js }}"></script>
</head>
<body>
    <div id="root"></div>
</body>
</html>
