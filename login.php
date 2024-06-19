<?php
session_start();

if (isset($_COOKIE["Credentials"])) {
    header('Location: chatapp.php');
    exit;
}

$encryptor = "";

$servername = "";
$username = "";
$password = "";
$dbname = "";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["login"])) {
        $email = $_POST["email"];
        $password = $_POST["password"];

        $sql_login = "SELECT * FROM User WHERE email = '$email' AND Password = '$password'";
        $result_login = $conn->query($sql_login);

        if ($result_login->num_rows == 1) {
            $row_list = $result_login->fetch_assoc();

            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $row_list["Username"];

            $conn->query(sprintf("UPDATE User SET IP = '%s' WHERE Snowflake = '%s'", $_SERVER['REMOTE_ADDR'], $row_list['Snowflake']));

            $value = encryptString($email . "||" . $password, $encryptor);

            setcookie("Credentials", $value, time() + 72000, "/", "domain");

            header("Location: chatapp.php");
            exit;
        } else {
            $login_error = '<div class="alert alert-primary alert-dismissible" role="alert">Invalid credentials! <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    }
    if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["username"]) && isset($_POST["register"])) {
        $email = $_POST["email"];
        $password = $_POST["password"];
        $username = $_POST["username"];
        $new_sf = randomSnowflake();

        $sql_reg = sprintf("INSERT INTO User 
            (Snowflake, Username, Email, Password, IP)
            SELECT * FROM (SELECT '%s' AS Snowflake, '%s' AS Username, '%s' AS Email, '%s' AS Password, '%s' AS IP) AS tmp
            WHERE NOT EXISTS (
            SELECT Username, Email FROM User WHERE (Username = '$username' OR Email = '$email')
            ) LIMIT 1", $new_sf, $username, $email, $password, $_SERVER['REMOTE_ADDR']);
        $conn->query($sql_reg);

        $sql_check = sprintf("SELECT * FROM User WHERE Snowflake = '%s'", $new_sf);
        $result_check = $conn->query($sql_check);

        if ($result_check->num_rows == 1) {
            $row_list = $result_check->fetch_assoc();

            $_SESSION["loggedin"] = true;
            $_SESSION["username"] = $row_list["Username"];
            ;

            $value = encryptString($email . "||" . $password, $encryptor);

            setcookie("Credentials", $value, time() + 72000, "/", "school.hallotheengineer.com");

            header("Location: chatapp.php");
            exit;
        } else {
            $login_error = "Something went wrong. Please try again.";
        }
    }

}


function randomSnowflake()
{
    $n = [14];
    for ($i = 0; $i < 15; $i++) {
        $n[$i] = rand(0, 9);
    }
    return implode($n);
}
function encryptString($plaintext, $password, $encoding = null)
{
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($plaintext, "AES-256-CBC", hash('sha256', $password, true), OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $ciphertext . $iv, hash('sha256', $password, true), true);
    return $encoding == "hex" ? bin2hex($iv . $hmac . $ciphertext) : ($encoding == "base64" ? base64_encode($iv . $hmac . $ciphertext) : $iv . $hmac . $ciphertext);
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        .back {
            background: #e2e2e2;
            width: 100%;
            position: absolute;
            top: 0;
            bottom: 0;
        }

        .div-center {
            width: 400px;
            height: 400px;
            background-color: #fff;
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            bottom: 0;
            margin: auto;
            max-width: 100%;
            max-height: 100%;
            overflow: auto;
            padding: 1em 2em;
            border-bottom: 2px solid #ccc;
            display: table;
        }

        div.content {
            display: table-cell;
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div class="back">
        <div class="div-center">
            <div class="content">
                <ul class="nav nav-pills nav-fill mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-login-tab" data-bs-toggle="pill" data-bs-target="#pills-login" role="tab" aria-controls="pills-login" aria-selected="true" >Login</button>
                    </li>
                    <li class="nav-item">
                    <button class="nav-link" id="pills-register-tab" data-bs-toggle="pill" data-bs-target="#pills-register" role="tab" aria-controls="pills-register" aria-selected="true">Register</button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    <div id="pills-login" class="tab-pane fade show active" role="tabpanel" aria-labelledby="pills-login-tab">
                        <h3>Sign In</h3>
                        <?php if (isset($login_error))
                            echo $login_error; ?>
                        <hr />
                        <form autocomplete="off" method="post"
                            action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="email">E-Mail:</label>
                                <input autocomplete="false" type="text" id="email" name="email" required
                                    class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="password">Passwort:</label>
                                <input autocomplete="false" type="password" id="password" name="password" required
                                    class="form-control">
                            </div>
                            <input type="submit" class="btn btn-primary mt-2" value="Login" name="login">
                            <hr />
                        </form>
                    </div>
                    <div id="pills-register" class="tab-pane fade show" role="tabpanel" aria-labelledby="pills-register-tab">
                        <h3>Sign Up</h3>
                        <?php if (isset($login_error))
                            echo $login_error; ?>
                        <hr />
                        <form autocomplete="off" method="post"
                            action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input autocomplete="false" type="text" id="username" name="username" required
                                    class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="email">E-Mail:</label>
                                <input autocomplete="false" type="text" id="email" name="email" required
                                    class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="password">Passwort:</label>
                                <input autocomplete="false" type="password" id="password" name="password" required
                                    class="form-control">
                            </div>
                            <input type="submit" value="Register" name="register" class="btn btn-primary mt-2">
                            <hr />
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>