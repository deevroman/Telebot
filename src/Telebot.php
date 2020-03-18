<?php
/*
 * @version 0.1.3
 */

namespace TeleBot {

    use Exception;
    use Localizer;
    use SimpleMySQL;
    use MongoDB;

    class TeleBot
    {
        private $website = 'https://api.telegram.org/bot';
        private $token;
        private $proxy = NULL;
        private $defaultChatId = NULL;
        private $updateHandler = NULL;
        private $DEBUG_TG_ID = NULL;
        private $localizer = NULL;
        public $logChat = NULL;


        /**
         * @var SimpleMySQL|null
         */
        private $database = NULL;

        /**
         * TeleBot constructor.
         * @param $token
         * @param null|SimpleMySQL|MongoDB $DBSettings create db connection
         * @param null $logChat
         * @param null $DEBUG_TG_ID
         * @param Localizer $localizer
         * @param string $mode
         */

        function __construct($token,
                             $DBSettings = NULL,
                             $logChat = NULL,
                             $DEBUG_TG_ID = NULL,
                             Localizer $localizer = NULL,
                             $mode = 'webhook') {
            $this->token = $token;
            $this->website .= $token;
            if ($DBSettings) {
                if (isset($DBSettings['mongodb'])) {
                    $this->database = new MongoDB\Client($DBSettings['mongodb']['mongo_url']);
                }
                else {
                    $this->database = new SimpleMySQL($DBSettings);
                }
            }
            $this->logChat = $logChat;
            $this->DEBUG_TG_ID = $DEBUG_TG_ID;
            $this->localizer = $localizer;
        }

        function __destruct() {
            $this->stop();
        }

        public function stop() {
            if ($this->database) {
                $this->database->close(); // FIXME
                $this->database = NULL;
            }
        }

        public function ans($str): string {
            return $this->localizer->get($str);
        }

        public function setLang(string $lang) {
            $this->localizer->setDefaultLang($lang);
        }

        public function setProxy($proxy) {
            $this->proxy = $proxy;
        }

        public function setDefaultChatId($chatId) {
            $this->defaultChatId = $chatId;
        }

        public function setDatabase($DB) {
            $this->database = new SimpleMySQL(['mysqli' => $DB]);
        }

        public function setHandler($handler) {
            $this->updateHandler = $handler;
        }

        public function run($input) {
            ($this->updateHandler)($input, $this);
        }

        public function DB() {
            return $this->database;
        }

        static function escapePreString(string $str) {
            return strtr($str, ["`" => "\`", "\\" => "\\\\"]);
        }

        public function uploadFile($url, $file_contents, $mime_type) {
            $multipart_boundary = '--------------------------' . microtime(true);
            $header = 'Content-Type: multipart/form-data; boundary=' . $multipart_boundary;

            $content = "--$multipart_boundary\r\n" .
                "Content-Disposition: form-data; name=\"file\"; filename=\"blob\"\r\n" .
                "Content-Type: $mime_type\r\n\r\n" .
                $file_contents . "\r\n";
            $content .= "--$multipart_boundary--\r\n";

            $aHTTP = array(
                'http' =>
                    array(
                        'method' => 'POST',
                        'header' => $header,
                        'content' => $content,
                        'ignore_errors' => true
                    )
            );
            if (!is_null($this->proxy)) {
                $aHTTP['http']['proxy'] = $this->proxy;
                $aHTTP['http']['request_fulluri'] = true;
            }
            $result = @file_get_contents($url, false, stream_context_create($aHTTP));
            if (!empty($http_response_header)) {
                preg_match('/\d{3}/', $http_response_header[0], $out);
            }
            else {
                return -1;
            }
            return ($out[0][0] == '2') ? $result : $out[0]; // код запросов 2xx
        }

        private function sendData($url, $post_fields) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Content-Type:multipart/form-data"
            ));
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            if (!is_null($this->proxy)) {
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            }
            $res = curl_exec($ch);
            if ($res === false) {
                throw new Exception(curl_error($ch));
            }
            return $res;
        }

        private function sendPost($url, $data = []) {
            $aHTTP = array(
                'http' =>
                    array(
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => http_build_query($data),
                        'protocol_version' => 1.1,
                        'follow_location' => 1,
                        'ignore_errors' => true
                    )
            );
            if (!is_null($this->proxy)) {
                $aHTTP['http']['proxy'] = $this->proxy;
                $aHTTP['http']['request_fulluri'] = true;
            }
            $result = @file_get_contents($url, false, stream_context_create($aHTTP));
            if (!empty($http_response_header)) {
                preg_match('/\d{3}/', $http_response_header[0], $out);
            }
            else {
                return -1;
            }
            if ($out[0][0] == '2') {
                return $result;
            }
            else {
                throw new Exception("$url\n" . print_r($data, true) . "\n" . $result);
            }
        }

        public function sendMessage($text, $args = []) {
            if (is_array($text)) {
                $args = $text;
                // old style TODO loging
            }
            else {
                if (!isset($args['chat_id'])) {
                    if (isset($this->defaultChatId)) {
                        $args['chat_id'] = $this->defaultChatId;
                    }
                    else {
                        throw new Exception("chat_id not unspecified");
                    }
                }
                if ($text) {
                    $args['text'] = $text;
                }
                else {
                    throw new Exception("text not unspecified");
                }
            }
            return $this->sendPost("{$this->website}/sendMessage", $args);
        }

        public function forwardMessage($args) {
            return $this->sendPost("{$this->website}/forwardMessage", $args);
        }

        public function editMessageText($args) {
            return $this->sendPost("{$this->website}/editMessageText", $args);
        }

        public function deleteMessage($args) {
            return $this->sendPost("{$this->website}/deleteMessage", $args);
        }

        public function restrictChatMember($args) {
            return $this->sendPost("{$this->website}/restrictChatMember", $args);
        }

        public function answerCallbackQuery($args) {
            return $this->sendPost("{$this->website}/answerCallbackQuery", $args);
        }

        public function answerInlineQuery($args) // TODO
        {
            return $this->sendPost("{$this->website}/answerInlineQuery", $args);
        }

        public function getChatMember($args) {
            return $this->sendPost("{$this->website}/getChatMember", $args);
        }

        public function getChatAdministrators($args) {
            return $this->sendPost("{$this->website}/getChatAdministrators", $args);
        }

        public function getMe() {
            return $this->sendPost("{$this->website}/getMe");
        }

        public function getFile($args) {
            return $this->sendPost("{$this->website}/getFile?", $args);
        }

        public function getFilePath($path) {
            return "https://api.telegram.org/file/bot{$this->token}/" . $path;
        }

        public function checkAdminRules($chat_id, $user_id) {
            $status = json_decode($this->getChatMember(['chat_id' => $chat_id, 'user_id' => $user_id]))->result->status;
            return $status == "creator" || $status == "administrator";
        }

        public function uploadStickerFile($args, $path) {
            $url = $this->website . "/uploadStickerFile?user_id=" . $args['user_id'];
            $args['png_sticker'] = new \CURLFile(realpath($path));
            return $this->sendData($url, $args);
        }

        public function sendSticker($args, $path = FALSE) {
            if ($path === FALSE) {
                return $this->sendPost("{$this->website}/sendSticker?", $args);
            }
            elseif ($path !== FALSE) {
                $url = $this->website . "/sendSticker?chat_id=" . $args['chat_id'];
                $args['sticker'] = new \CURLFile(realpath($path));
                return $this->sendData($url, $args);
            }
        }

        public function sendPhoto($args, $path = FALSE, $content = NULL) { // TODO $content
            if ($path === FALSE) {
                return $this->sendPost("{$this->website}/sendPhoto?", $args);
            }
            elseif ($path !== FALSE) {
                $url = $this->website . "/sendPhoto?chat_id=" . $args['chat_id'];
                $args['photo'] = new \CURLFile(realpath($path));
                return $this->sendData($url, $args);
            }/* else {
                $url = $this->website . "/sendPhoto?chat_id=" . $args['chat_id'];
                $args['photo'] = $content;
                return $this->sendData($url, $args);
            }*/
        }

        public function sendVoice($args, $path = FALSE) {
            if ($path === FALSE) {
                return $this->sendPost("{$this->website}/sendVoice?", $args);
            }
            else {
                $url = $this->website . "/sendVoice?" . http_build_query($args);
                // $args['voice'] = new CURLFile(realpath($path));
                return $this->uploadFile($url, file_get_contents(realpath($path)), "audio/opus");
            }
        }

        public function sendChatAction($args) {
            return $this->sendPost("{$this->website}/sendChatAction?", $args);
        }

        public function setWebhook($args) {
            return $this->sendPost("{$this->website}/setWebhook?", $args);
        }

        public function getWebhookInfo($args = []) {
            return $this->sendPost("{$this->website}/getWebhookInfo?");
        }

    }

    class Keyboard
    {
        private $keyboard = [];
        private $type;
        private $count = 0;

        function __construct($type, $button = NULL) {
            $this->type = $type;
            if ($button !== NULL) {
                $this->add(0, $button);
            }
        }

        public function makeTextKeyboard($keyboard) {
            $line = 0;
            $i = 0;
            $this->keyboard = [];
            foreach ($keyboard as $item) {
                foreach ($item as $but) {
                    $this->add($line, new Button($but, (string)$i));
                    ++$i;
                }
                ++$line;
            }
        }

        public function view() {
            return json_encode([$this->type => array_values($this->keyboard)]);
        }

        public function add($line, ...$buttons) {
            foreach ($buttons as $item) {
                $this->keyboard[$line] [] = $item->getButton();
                $this->count++;
            }
        }

        public function countLine() {
            return count($this->keyboard);
        }

        public function count() {
            return $this->count;
        }
    }

    class Button
    {
        private $button = [];

        function __construct($text, $cb = NULL, $url = NULL, $switchInlineQuery = NULL) {
            $this->button['text'] = $text;
            if (isset($cb)) $this->button['callback_data'] = $cb;
            if (isset($url)) $this->button['url'] = $url;
            if (isset($switchInlineQuery)) $this->button['switch_inline_query'] = $switchInlineQuery;
        }

        public function getButton() {
            return $this->button;
        }
    }


}