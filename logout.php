<?php

//log the user out by clearing the whole session, then go back to the home page
session_start();
session_destroy();
header("location:index.html");

?>
