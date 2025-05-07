-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Май 06 2025 г., 09:31
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `metro`
--

-- --------------------------------------------------------

--
-- Структура таблицы `employee`
--

CREATE TABLE `employee` (
  `id` int(11) NOT NULL,
  `id_station` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `employment`
--

CREATE TABLE `employment` (
  `id` int(11) NOT NULL,
  `id_employee` int(11) NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `statemants`
--

CREATE TABLE `statemants` (
  `id` int(11) NOT NULL,
  `bagage` text NOT NULL,
  `date` date NOT NULL,
  `status` text NOT NULL,
  `id_station_start` int(11) NOT NULL,
  `id_station_end` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_employee` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `station`
--

CREATE TABLE `station` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `line` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `password` text NOT NULL,
  `email` text NOT NULL,
  `firstname` varchar(20) NOT NULL DEFAULT 'Имя',
  `lastname` varchar(20) NOT NULL DEFAULT 'Фамилия',
  `mobility` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_station` (`id_station`);

--
-- Индексы таблицы `employment`
--
ALTER TABLE `employment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_employee` (`id_employee`);

--
-- Индексы таблицы `statemants`
--
ALTER TABLE `statemants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_employee` (`id_employee`),
  ADD KEY `id_station_start` (`id_station_start`),
  ADD KEY `id_station_end` (`id_station_end`),
  ADD KEY `id_user` (`id_user`);

--
-- Индексы таблицы `station`
--
ALTER TABLE `station`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_idx` (`email`) USING HASH;

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `employee`
--
ALTER TABLE `employee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `employment`
--
ALTER TABLE `employment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `statemants`
--
ALTER TABLE `statemants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `station`
--
ALTER TABLE `station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`id_station`) REFERENCES `station` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `employment`
--
ALTER TABLE `employment`
  ADD CONSTRAINT `employment_ibfk_1` FOREIGN KEY (`id_employee`) REFERENCES `employee` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `statemants`
--
ALTER TABLE `statemants`
  ADD CONSTRAINT `statemants_ibfk_1` FOREIGN KEY (`id_employee`) REFERENCES `employee` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `statemants_ibfk_2` FOREIGN KEY (`id_station_start`) REFERENCES `station` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `statemants_ibfk_3` FOREIGN KEY (`id_station_end`) REFERENCES `station` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `statemants_ibfk_4` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
