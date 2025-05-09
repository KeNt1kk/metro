document.getElementById('statemantForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        startStation: document.getElementById('startStation').value,
        endStation: document.getElementById('endStation').value,
        baggageAvailability: document.getElementById('baggageAvailability').value,
        dateStatemant: document.getElementById('dateStatemant').value
    };
    
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
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
});