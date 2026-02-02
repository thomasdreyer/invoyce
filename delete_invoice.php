<?php
require './config/db.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
