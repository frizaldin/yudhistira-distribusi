<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>CRM Percetakan Buku - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <!-- App CSS -->
    <link rel="stylesheet" href="{{ asset('/assets/css/style.css') }}?v={{ time() }}" />

</head>

<body>
    <div class="login-container">
        <!-- Left Panel - Illustration -->
        <div class="login-left">
            <div class="illustration-wrapper">
                <div class="illustration-text">
                    Kelola penjualan dan distribusi<br />
                    <span class="highlight">Buku</span> ke seluruh
                    <span class="highlight">Sekolah</span> dengan mudah!
                </div>
                <div class="illustration-placeholder">
                    <div>
                        <i class="bi bi-journals" style="font-size: 4rem; opacity: 0.3"></i>
                        <p style="margin-top: 1rem; opacity: 0.5">CRM Percetakan Buku</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="login-right">
            <div class="login-form-wrapper">
                <div class="login-logo">
                    <img src="image/logo.png" alt="Logo" style="width: 70%" />
                </div>

                <h2 class="login-greeting">Hai, selamat datang kembali</h2>

                <form id="_loginForm" action="{{ url('_login') }}" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" placeholder="Masukkan email" name="email" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Masukkan kata sandi kamu</label>
                        <div class="password-wrapper">
                            <input type="password" class="form-control" id="passwordInput"
                                placeholder="Masukkan password" name="password" />
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye-slash" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login">
                        Masuk
                    </button>

                    <div class="remember-device">
                        <input type="checkbox" id="rememberDevice" checked />
                        <label for="rememberDevice">Ingat perangkat ini</label>
                    </div>

                    <div class="terms-text">
                        Dengan melanjutkan, kamu menerima
                        <a href="#">Syarat Penggunaan</a> dan
                        <a href="#">Kebijakan Privasi</a> kami.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('/assets/js/app.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('/js/auth/signin.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('/js/all.js') }}?v={{ time() }}"></script>
</body>

</html>
