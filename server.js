const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const mysql = require('mysql2');

const app = express();
const port = 3000;

// Создаем соединение с базой данных
const connection = mysql.createConnection({
  host: 'localhost',
  user: 'root',      // Имя пользователя MySQL
  password: '',      // Пароль пользователя MySQL
  database: 'metro'    // Имя базы данных
});

// Подключаемся к базе данных
connection.connect(err => {
  if (err) {
    return console.error('Ошибка при подключении к базе данных:', err.message);
  }
  console.log('Подключение к базе данных успешно!');
});

app.use(cors());
app.use(bodyParser.json());
app.use(express.static('public'));

app.get('/data', (req, res) => {
  connection.query('SELECT * FROM users', (err, results) => {
    if (err) {
      return res.status(500).json({ error: err.message });
    }
    res.json(results);
  });
});

app.post('/add', (req, res) => {
  const { firstName, lastName } = req.body;
  connection.query('INSERT INTO users (firstname, lastname) VALUES (?, ?)', [firstName, lastName], (err, results) => {
    if (err) {
      return res.status(500).json({ error: err.message });
    }
    // Получаем обновленный список пользователей
    connection.query('SELECT * FROM users', (err, results) => {
      if (err) {
        return res.status(500).json({ error: err.message });
      }
      res.json(results);
    });
  });
});

app.listen(port, () => {
  console.log(`Server running at http://localhost:${port}`);
});