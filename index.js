document.addEventListener('DOMContentLoaded', function() {
    const loginBtn = document.getElementById('loginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            checkSession();
        });
    }
});

function checkSession() {
    fetch('/api/check_session.php')
        .then(response => response.json())
        .then(data => {
            if (data.is_logged_in && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else{
                window.location.href = '/public/login.html'
            }
        })
        .catch(error => {
            console.error('Ошибка проверки сессии:', error);
            window.location.href = '/public/login.html';
        });
}