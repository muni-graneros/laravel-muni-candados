<link rel="manifest" href="/manifest-app.webmanifest">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw-app.js', { scope: '/app/' }).catch(() => {});
  }
</script>
