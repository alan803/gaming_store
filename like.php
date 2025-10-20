<?php
    session_start();
    include 'connection.php';
    if(!isset(SESSION['user_id']))
    {
        header("Location: login.php");
        exit();
    }
    
?>