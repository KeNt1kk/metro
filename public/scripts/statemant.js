document.getElementById('statemantForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        startStation: document.getElementById('startStation').value,
        endStation: document.getElementById('endStation').value,
        baggageAvailability: document.getElementById('baggageAvailability').checked ? 1 : 0,
        dateStatemant: document.getElementById('dateStatemant').value
    };
    console.log(formData);
    fetch('/api/send_statemant.php', {
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
            window.location.href = '/public/profile.php';
        } else if(!data.empty) {
            const userConfirmed = confirm(data.message + '\n\nХотите перейти в профиль для отслеживания статуса?');
            if (userConfirmed) {
                window.location.href = '/public/profile.php';
            }
        }else{
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Произошла ошибка при отправке заявки');
    });
});