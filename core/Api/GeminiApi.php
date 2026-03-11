<?php
namespace Meteora\Core\Api;

class GeminiApi {
    public static function generateContent($prompt, $api_key, $temperature = 0.2, $json_only = true) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent?key=" . $api_key;

        $config = ["temperature" => $temperature];
        if ($json_only) {
            $config["response_mime_type"] = "application/json";
        }

        $body = json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => $config
        ]);

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $body,
            'timeout' => 120
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', "Errore di Rete: " . $response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return new \WP_Error('api_error', strval($http_code));
        }

        $res_body = json_decode(wp_remote_retrieve_body($response), true);
        $raw_json = $res_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if ($json_only) {
            preg_match('/\{.*\}/s', $raw_json, $matches);
            if (!empty($matches)) {
                return $matches[0];
            }

            preg_match('/\[.*\]/s', $raw_json, $matches);
            if (!empty($matches)) {
                return $matches[0];
            }
        }

        return $raw_json;
    }
}
