<!DOCTYPE html>
<html>
<head>
  <title>Öffne...</title>
</head>
<body>
<script>
  const token = new URLSearchParams(window.location.search).get('token');
  if (token) {
    window.open('update.php?token=' + encodeURIComponent(token), '_blank');
    window.close();
  } else {
    document.body.innerHTML = 'Ungültiger Link.';
  }
</script>
</body>
</html>
