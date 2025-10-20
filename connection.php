<?php
    $server = "localhost";
    $username = "root";
    $password = "";
    $database = "game_gear";
    $conn=mysqli_connect($server,$username,$password,$database);
    if(!$conn)
    {
        echo "unable to connect";
    }
?>