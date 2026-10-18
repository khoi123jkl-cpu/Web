const authShell = document.getElementById("authShell");

document.querySelectorAll("[data-show]").forEach((button) => {
    button.addEventListener("click", () => {
        authShell.classList.toggle("show-register", button.dataset.show === "register");
    });
});

const registerForm = document.querySelector(".register-form form");
if (registerForm) {
    registerForm.addEventListener("submit", (e) => {
        const pwd = document.getElementById("pwd").value;
        const confirmPwd = document.getElementById("confirmation_pwd").value;
        if (pwd.length < 8) {
            alert("Mật khẩu phải có ít nhất 8 ký tự.");
            e.preventDefault();
            return;
        }
        if (pwd !== confirmPwd) {
            alert("Mật khẩu và xác nhận mật khẩu không trùng khớp.");
            e.preventDefault();
            return;
        }
    });
}