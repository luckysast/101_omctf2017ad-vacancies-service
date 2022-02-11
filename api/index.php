<?php

class Resume {
    private $fields = array('first_name', 'last_name', 'phone');

    private $response = array();
    private $url = '';

    private $dsn = 'mysql:dbname=careers;host=omctf-mysql;port=3306';
    private $user = 'careers';
    private $pass = 'careers';

    private function validate($post, $files) {
        $valid = true;
        foreach ($this->fields as $field) {
            if (empty($post[$field])) {
                if ($valid) {
                    $this->response[] = '<h2 class="text-center text-danger">Пожалуйста, заполните форму корректно!</h2>';
                }
                $this->response[] = '<p class="text-center text-danger">Не могу найти поле "' . $field . '"</p>';
                $valid = false;
            }
        }

        if (!isset($files['resume-file'])) {
            $this->response[] = '<p class="text-center text-danger">Файл не отправлен!</p>';
            $valid = false;
        }

        if (!isset($files['resume-file']['tmp_name']) && !file_exists($files['resume-file']['tmp_name'])) {
            $this->response[] = '<p class="text-center text-danger">Ошибка при загрузке файла</p>';
            $valid = false;
        }

        return $valid;
    }

    private function initDB() {
        $pdo = new \PDO($this->dsn, $this->user, $this->pass, array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT => true
        ));
        if (!($pdo instanceof \PDO)) {
            $this->response[] = '<p class="text-center text-danger">Простите, кажется, сейчас не получится. Попробуйте в другое время</p>';
            return false;
        }

        $pdo->exec(<<<EOL
CREATE TABLE IF NOT EXISTS `resume` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `phone` text COLLATE utf8_unicode_ci NOT NULL,
  `resume-file` text COLLATE utf8_unicode_ci NOT NULL,
  `hash` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
EOL
);

        $pdo->exec('SET NAMES "utf8";');

        return $pdo;
    }

    private function random() {
        $time = microtime(true);
        $rnd = (13 * ((10000 * microtime(true) % 4199 + 139) * 19) - 1) * 17 % 46189;
        return $rnd;
    }

    private function storeInDB($post, $destination) {
        $pdo = $this->initDB();
        if (false !== $pdo) {
            $sql = <<<EOL
INSERT INTO `resume` (`id`, `first_name`, `last_name`, `phone`, `resume-file`, `hash`) 
VALUES (NULL, :first_name, :last_name, :phone, :destination, :hash);
EOL;

            if ($stmt = $pdo->prepare($sql)) {
                $hash = md5($this->random());
                $stmt->execute(array(
                    ':first_name' => $post['first_name'],
                    ':last_name' => $post['last_name'],
                    ':phone' => $post['phone'],
                    ':destination' => $destination,
                    ':hash' => $hash
                ));

                $this->url = "preview.html?hash={$hash}&id={$pdo->lastInsertId()}";

                $this->response[] = '<h2 class="text-center text-success">Файл успешно загружен! Мы с Вами свяжемся!</h2>';
                $this->response[] = '<p class="text-center text-success">Ответ на Ваше резюме будет так же доступен по <a href="' .
                    $this->url  .'">этой ссылке</a></p>';
            } else {
                $this->response[] = '<p class="text-center text-danger">Простите, кажется, сейчас не получится. Попробуйте в другое время</p>';
            }
        }
    }

    private function save($post, $files) {
        $source = $files['resume-file']['tmp_name'];
        $destination = implode(DIRECTORY_SEPARATOR, array(
            dirname(__FILE__),
            "resumes",
            $files['resume-file']['name']
        ));
        if (!is_uploaded_file($source) || !move_uploaded_file($source, $destination)) {
            $this->response[] = '<p class="text-center text-danger">Ошибка при загрузке файла!</p>';
        } else {
            $this->storeInDB($post, $destination);
        }
    }

    public function process($post, $files) {
        if ($this->validate($post, $files)) {
            $this->save($post, $files);
        }

        return json_encode(array(
            'message' => implode("\n", $this->response),
            'url' => $this->url
        ));
    }

    public function show($id, $hash) {
        $pdo = $this->initDB();
        if (false !== $pdo) {
            $sql = "SELECT * FROM `resume` WHERE `id`='{$id}' AND `hash`='{$hash}'";

            if (($stmt = $pdo->prepare($sql)) && ($stmt->execute()) && ($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
                echo json_encode($row);
                die;
            }
        }

        header("HTTP/1.0 404 Not Found");
        die;
    }
}

$resume = new Resume();

if (isset($_GET['id']) && isset($_GET['hash'])) {
    $resume->show(intval($_GET['id']), $_GET['hash']);
} else {
    echo $resume->process($_POST, $_FILES);
}