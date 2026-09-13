const authShell = document.getElementById("authShell");

document.querySelectorAll("[data-show]").forEach((button) => {
    button.addEventListener("click", () => {
        authShell.classList.toggle("show-register", button.dataset.show === "register");
    });
});