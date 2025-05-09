document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        email: document.getElementById('emaliRegister').value,
        password: document.getElementById('passwordRegister').value,
        name: document.getElementById('nameRegister').value,
        surname: document.getElementById('surnameRegister').value,
        repeatPassword: document.getElementById('repeatPasswordRegister').value,
        mobility: document.getElementById('mobilityRegister').value
    };
    
    fetch('/api/register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = '/profile.php';
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
});