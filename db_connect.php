<?php 

$conn = new mysqli('localhost', 'root', '', 'house_rental_db');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}