@php($isAdminApp = request()->is('admin') || request()->is('admin/*'))
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#a30f31" />
    <meta name="color-scheme" content="light" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="{{ $isAdminApp ? 'EMC Admin' : 'EMC' }}" />
    <meta name="format-detection" content="telephone=no" />
    <meta name="description" content="Book trusted shoe cleaning and repair with EMC Shoes Care Myanmar." />
    <link rel="icon" type="image/jpeg" href="{{ rtrim(request()->getBaseUrl(), '/') }}/emcicon.jpg" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ rtrim(request()->getBaseUrl(), '/') }}/apple-touch-icon.png" />
    <link rel="manifest" href="{{ rtrim(request()->getBaseUrl(), '/') }}/{{ $isAdminApp ? 'manifest-admin.webmanifest' : 'manifest.webmanifest' }}" />
    <title>{{ $isAdminApp ? 'EMC Shoes Care Admin' : 'EMC Shoes Care Myanmar' }}</title>
    @viteReactRefresh
    @vite('resources/js/main.jsx')
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
