<?php

$conn_ajax = new mysqli("localhost", "root", "", "ajax_projects_db");

if ($conn_ajax->connect_error) {
    die("Ajax DB connection failed");
}
