<?php
namespace Meteora\Core\Api;

class DeepSeekApi {
    public static function generateContent($prompt, $api_key, $temperature = 0.2, $json_only = true) {
        $url = "https://api.deepseek.com/chat/completions";

        $body = [
            "model" => "deepseek-chat",
            "messages" => [
                [
                    "role" => "system",
                    "content" => "You are a helpful assistant." . ($json_only ? " You must only reply in valid JSON format without markdown wrappers." : "")
                ],
                [
                    "role" => "user",
                    "content" => $prompt
                ]
            ],
            "temperature" => $temperature
        ];

        if ($json_only) {
            $body["response_format"] = ["type" => "json_object"];
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body'    => json_encode($body),
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', "Errore di Rete: " . $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return new \WP_Error('api_error', "DeepSeek API Error: " . strval($http_code) . " - " . wp_remote_retrieve_body($response));
        }

        $raw_body = wp_remote_retrieve_body($response);
        $res_body = json_decode($raw_body, true);

        if (!is_array($res_body) || !isset($res_body['choices'][0]['message']['content'])) {
            return new \WP_Error('api_error', "Risposta DeepSeek non valida o non in formato JSON. Dati ricevuti: " . esc_html(substr($raw_body, 0, 200)));
        }

        $raw_text = $res_body['choices'][0]['message']['content'];

        if ($json_only) {
            preg_match('/\{.*\}/s', $raw_text, $matches);
            if (!empty($matches)) {
                return $matches[0];
            }

            preg_match('/\[.*\]/s', $raw_text, $matches);
            if (!empty($matches)) {
                return $matches[0];
            }
        }

        return $raw_text;
    }
}
