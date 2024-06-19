<?php
    session_start();

    $decryptor = "";

    $servername = "";
    $username = "";
    $password = "";
    $dbname = "";

    $messages_cache = array();

    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8");

    if ($conn->connect_error) {
        die("Connection error: " . $conn->connect_error);
    }
	if (!array_key_exists("Credentials", $_COOKIE)) reject();

	$credentials = explode("||", decryptString($_COOKIE["Credentials"], $decryptor));

	if (!(count($credentials) > 1)) {
        unset($_COOKIE['Credentials']);
        setcookie('Credentials', '', -1, '/');
        reject();
    }
    
    $sql_login = sprintf("SELECT * FROM User WHERE Password = '%s' AND Email = '%s'", $credentials[1], $credentials[0]);
    $result_login = $conn->query($sql_login);

    if (!($result_login->num_rows > 0)) {
        unset($_COOKIE['Credentials']);
        setcookie('Credentials', '', -1, '/');
        reject();
    }
    $current_user = $result_login->fetch_assoc();
    $current_user_id = $current_user["Snowflake"];

    $channelSql = sprintf("SELECT * FROM Chat INNER JOIN memberOfChat ON Chat.Snowflake = memberOfChat.ChatSnowflake WHERE memberOfChat.UserSnowflake = '%s'", $current_user_id);
    
    $channels = $conn->query($channelSql);

    $usersSql = "SELECT Snowflake, Username FROM User";

    $users = $conn->query($usersSql);
    
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["user"])) {
            $user = $_POST["user"];

            $sql_check = sprintf("SELECT COUNT(*) AS chat_exists
            FROM memberOfChat ucp1
            JOIN memberOfChat ucp2 ON ucp1.ChatSnowflake = ucp2.ChatSnowflake
            WHERE (ucp1.UserSnowflake = '%s' AND ucp2.UserSnowflake = '%s')
               OR (ucp1.UserSnowflake = '%s' AND ucp2.UserSnowflake = '%s');", $_POST["user"], $current_user_id, $current_user_id, $_POST["user"]);
            $result_check = $conn->query($sql_check);

            $pair_count = $result_check->fetch_assoc()["chat_exists"];

            if ($pair_count > 0 || $user == $current_user_id) {
                echo '<div class="alert alert-primary alert-dismissible" role="alert">You have already created a chat with this user or are prohibited! <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } else {
                $new_chat_sf = randomSnowflake();
                echo $new_chat_sf;
                $sql_create = sprintf("INSERT INTO Chat (Snowflake) VALUES ('%s')", $new_chat_sf);
                $sql_add_1 = sprintf("INSERT INTO memberOfChat (UserSnowflake, ChatSnowflake) VALUES ('%s', '%s')", $_POST["user"], $new_chat_sf);
                $sql_add_2 = sprintf("INSERT INTO memberOfChat (UserSnowflake, ChatSnowflake) VALUES ('%s', '%s')", $current_user_id, $new_chat_sf);
                $conn->query($sql_create);
                $conn->query($sql_add_1);
                $conn->query($sql_add_2);

                unset($_POST);
                header("Refresh:0");
            }
        }
        if (isset($_POST["content"])) {
            $content = $_POST["content"];
            $rand_sf = randomSnowflake();

            if (isset($_SESSION["chat"])) {
                $sql_insert = sprintf("INSERT INTO Message (Snowflake, Author, Content, ChatSnowflake) VALUES ('%s','%s','%s','%s')", $rand_sf, $current_user_id, $content, $_SESSION["chat"]);
                $conn->query($sql_insert);
            }
        }

    }
    if ($_SERVER["REQUEST_METHOD"] == "GET" || isset($_SESSION["chat"])) {
        $chat_sf = "";
        if (isset($_GET["channel"])) {
            $chat_sf = $_GET["channel"];
        } else if (isset($_SESSION["chat"])) {
            $chat_sf = $_SESSION["chat"];
        }
        unset($messages_cache);
        $messages_cache = array();

        $sql_perm = sprintf("SELECT COUNT(*) FROM memberOfChat m WHERE m.ChatSnowflake = '%s' AND m.UserSnowflake = '%s'", $chat_sf, $current_user_id);
        $res_perm = $conn->query($sql_perm);

        if ($res_perm->num_rows > 0) {
            $_SESSION["chat"] = $chat_sf;
            $offset = 0;
            $sql_messages = sprintf("SELECT * FROM Message WHERE ChatSnowflake = '%s' ORDER BY `Message`.`Timestamp` DESC LIMIT %s,20", $chat_sf, $offset);    
            $res_messages = $conn->query($sql_messages);
            while ($row = $res_messages->fetch_assoc()) {
                array_push($messages_cache,new Message($row["Snowflake"], $row["Author"], $row["Timestamp"], $row["Content"], $row["ChatSnowflake"]));
            }
        } else {
            die(401);
        }
    }

    function reject() {
        die("Invalid session. <a href='login.php'>Login again.</a>");
    }
    
    function randomSnowflake() { //No real Snowflake due to failing install of libraries
        $n = [14];
        for ($i = 0; $i < 15; $i++) {
            $n[$i] = rand(0,9);
        }
        return implode($n);
    }
    
    function decryptString($ciphertext, $password, $encoding = null) {
        $ciphertext = $encoding == "hex" ? hex2bin($ciphertext) : ($encoding == "base64" ? base64_decode($ciphertext) : $ciphertext);
        if (!hash_equals(hash_hmac('sha256', substr($ciphertext, 48).substr($ciphertext, 0, 16), hash('sha256', $password, true), true), substr($ciphertext, 16, 32))) return null;
        return openssl_decrypt(substr($ciphertext, 48), "AES-256-CBC", hash('sha256', $password, true), OPENSSL_RAW_DATA, substr($ciphertext, 0, 16));
    }

    class Message {
        public $snowflake;
        public $author;
        public $timestamp;
        public $content;
        public $chat_snowflake;

        public function __construct(string $snowflake, string $author, string $timestamp, string $content, string $chat_snowflake) {
            $this->snowflake = $snowflake;
            $this->author = $author;
            $this->timestamp = $timestamp;
            $this->content = $content;
            $this->chat_snowflake = $chat_snowflake;
        }
        public function getContent(): string {
            return $this->content;
        }
        public function getSnowflake(): string {
            return $this->snowflake;
        }
        public function getTimestamp(): string {
            return $this->timestamp;
        }
        public function getAuthor(): string {
            return $this->author;
        }
        public function getChatSnowflake(): string {
            return $this->chat_snowflake;
        }
        public function getAuthorName($conn): string {
            return $conn->query(sprintf("SELECT Username FROM User WHERE Snowflake = '%s'", $this->author))->fetch_assoc()["Username"];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatApp</title>
    <link rel="stylesheet" href="main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
        padding: 25px;
        background-color: #424549;
        color: #7289da;
        font-size: 25px;
        }
    </style>

</head>
<body data-bs-theme="dark">
    <a href="logout.php" class="link-danger">Logout</a>
    <div class="input-group mb-3">
        <div class="mb-3 mr-3">
            <label for="channel_form" class="form-label">Channels</label>
            <?php
                echo "<form method='get' action='".htmlspecialchars($_SERVER["PHP_SELF"])."' id='channel_form' class='form-control'>";
                echo "<select class='form-select form-select-lg mb-3' form='channel_form' name='channel'>";
                while ($channel = $channels->fetch_assoc()) {
                    $memberSql = sprintf("SELECT Username FROM User u INNER JOIN memberOfChat m ON u.Snowflake = m.UserSnowflake WHERE m.ChatSnowflake = '%s' AND u.Snowflake != ''", $channel["ChatSnowflake"], $current_user);
                    $res = $conn->query($memberSql);
                    $username = $res->fetch_assoc()["Username"];
                    echo "<option value='".$channel['ChatSnowflake']."'>".$username."</option>";
                }
                echo "</select>";
                echo "<input type='submit' class='btn btn-primary' value='Choose'>";
                echo "</form>";
            ?>
        </div>
        <div class="mb-3">
            <label for="create-chat" class="form-label">New Chat</label>
            <?php
                echo "<form action='".htmlspecialchars($_SERVER["PHP_SELF"])."' method='POST' id='create-chat' class='form-control'>";
                echo "<select name='user' class='form-select' aria-label='Choose a user to create a chat with'>";
                while ($user = $users->fetch_assoc()) {
                    echo sprintf("<option value='%s'>%s</option>", $user['Snowflake'], $user['Username']);
                }
                echo "</select>";
                echo "<input type='submit' value='Create Chat' class='btn btn-primary'>";
                echo "</form>";
            ?>
        </div>
    </div>
    <br>
        <h2>Chat</h2>
            <div class="containter ">
            <div class="row">
                <div class="col">
                    <form class="input-group mb-3" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post">
                        <input type="text" name="content" class="form-control" placeholder="Your message here"/>
                        <input type="submit" id="submit_message" value="SendMessage" class="btn btn-success"/>
                    </form>
                </div>
                <div class="col"></div>
                <div class="col"></div>
            </div>
            </div>
                <?php
                    for ($i = 0; $i < count($messages_cache); $i++) {
                        $msg = $messages_cache[$i];
                        $align = $msg->getAuthor() == $current_user_id ? "right" : "left";
                      	echo "<div class='row'>";
                      	echo "<div class='col'>";
                    	echo "<div class='card card-body'>";
                        echo "<div class='message'>";
                        echo "<div class='author ".$align."'>🗿 ".htmlspecialchars($msg->getAuthorName($conn))."</div>";
                        echo "<div class='content'>🧾 ".htmlspecialchars($msg->getContent())."</div>";
                        echo "<span class='time-".$align."'>🕐 ".htmlspecialchars($msg->getTimestamp())."</span>";
                      	echo "</div>";
                        echo "</div>";
                      	echo "</div>";
                      echo "<div class='col'></div>";
                      	echo "</div>";
                    }
                    ?>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>