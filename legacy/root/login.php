<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirish - samdu.system.uz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-5">

                    <div class="text-center mb-4">
                        <i class="fas fa-shield-alt text-primary" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">Tizimga kirish</h3>
                    </div>

                    <form id="loginForm">

                        <!-- USERNAME -->
                        <div class="mb-3">
                            <label for="loginUsername" class="form-label">Foydalanuvchi nomi</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="loginUsername" placeholder="Username kiriting" required>
                            </div>
                            <div class="invalid-feedback">Username kiritilishi shart</div>
                        </div>

                        <!-- PASSWORD -->
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">Parol</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="loginPassword" placeholder="********" required>
                            </div>
                            <div class="invalid-feedback">Parol kiritilishi shart</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Kirish
                        </button>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$('#loginForm').submit(function(e) {
    e.preventDefault();

    let username = $('#loginUsername').val().trim();
    let password = $('#loginPassword').val().trim();

    if(username === ""){
        Swal.fire({
            icon: 'warning',
            title: "Username kiriting!"
        });
        return;
    }

    if(password === ""){
        Swal.fire({
            icon: 'warning',
            title: "Parol kiritilishi shart!"
        });
        return;
    }

    $.ajax({
        url: "login_check.php",
        method: "POST",
        dataType: "json",
        data: {
            username: username,
            password: password
        },
        success: function(response){

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });

            if(response.error == 0){
                Toast.fire({ icon: 'success', title: response.message });

                setTimeout(() => {
                    window.location.href = "dashboard/index.php";
                }, 2000);

            } else {
                $('#loginPassword').val('');
                Toast.fire({ icon: 'error', title: response.message });
            }
        },
        error: function(){
            Swal.fire({
                icon: 'error',
                title: "Internet bilan muammo!",
                text: "Qaytadan urinib ko'ring!"
            });
        }
    });
});
</script>

</body>
</html>
