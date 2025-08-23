<?php
session_start();
if (isset($_POST['tab'])) {
    $_SESSION['active_tab'] = $_POST['tab'];
    echo 'OK';
}
?>