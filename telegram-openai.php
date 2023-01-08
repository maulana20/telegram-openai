<?php

class cURL
{
    public function __call($name, $arguments)
    {
        return $this->call($name, $arguments ? $arguments[0] : null);
    }
    
    public function call($method, $data = NULL)
    {
        $uri = is_a($this, "Telegram") ? "{$this->url}/bot{$this->token}/{$method}" . ($data ? "?" . http_build_query($data) : null) : "{$this->url}/{$method}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (is_a($this, "OpenAI")) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer {$this->key}"
            ]);
        }
        curl_setopt($ch, CURLOPT_REFERER, $this->url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_a($this, "Telegram") ? null : json_encode($data));
        curl_setopt($ch, CURLOPT_POST, true);
        
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }
}

class Telegram extends cURL
{
    protected $url;
    protected $token;
    
    public function __construct()
    {
         if (!getenv("TELEGRAM_TOKEN")) throw new Exception("TELEGRAM_TOKEN is empty");
         $this->url = "https://api.telegram.org";
         $this->token = getenv("TELEGRAM_TOKEN");
    }

}

class OpenAI extends cURL
{
    protected $url;
    protected $key;
    
    public function __construct()
    {
         if (!getenv("OPENAI_KEY")) throw new Exception("OPENAI_KEY is empty");
         $this->url = "https://api.openai.com/v1";
         $this->key = getenv("OPENAI_KEY");
    }
}

try {
    $telegram = new Telegram();
    $openai = new OpenAI();
    
    $me = $telegram->getMe()['result'];
    echo "selamat datang [{$me['first_name']}] [{$me['id']}] @{$me['username']} \n";
    
    while (true) {
        $data_update = $telegram->getUpdates()['result'];
        $result = $data_update ? current($data_update) : ['update_id' => 0];
        if ($result['update_id']) {
            echo "[{$result['message']['chat']['username']}] [{$result['message']['chat']['id']}] => {$result['message']['text']} \n";
            $telegram->sendChatAction([
                'chat_id' => $result['message']['chat']['id'],
                'action' => 'typing'
            ]);
            $telegram->sendMessage([
                'chat_id' => $result['message']['chat']['id'],
                'text' => preg_replace("/[\n\r]/", "", current($openai->completions([
                    'model' => "text-davinci-003",
                    'prompt' => $result['message']['text'],
                    'temperature' => 0.9,
                    'max_tokens' => 150
                ])['choices'])['text'])
            ]);
            $data = ['offset' => $result['update_id'] + 1];
            $telegram->getUpdates($data);
        } else {
            echo 'listening ..' . "\n";
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
    exit();
}
