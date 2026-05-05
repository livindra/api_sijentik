<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Status Akun</title>
</head>
<body>

<h2>Halo {{ $name }}</h2>

@if($status == 'pending')
<p>Akun Anda telah berhasil didaftarkan.</p>
<p>Saat ini akun Anda sedang menunggu persetujuan dari admin.</p>
@endif

@if($status == 'approved')
<p>Selamat! 🎉</p>
<p>Akun Anda telah <b>DITERIMA</b> oleh admin.</p>
<p>Anda sekarang dapat login ke aplikasi.</p>
@endif

@if($status == 'rejected')
<p>Mohon maaf.</p>
<p>Akun Anda <b>DITOLAK</b> oleh admin.</p>
<p>Silakan hubungi admin desa untuk informasi lebih lanjut.</p>
@endif

<br>

<p>Terima kasih.</p>
<p><b>Sistem SIDAK Desa</b></p>

</body>
</html>