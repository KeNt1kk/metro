document.getElementById('updateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        statement_id: document.getElementById('statement_id').value
    };
    
    fetch('/api/update_statemant.php', {
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
        }else{
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
});