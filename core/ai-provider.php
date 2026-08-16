<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AI 提供商抽象层
 *
 * 支持多种 AI 提供商：
 *  - custom_chat    : 自定义 Chat Completions（OpenAI 兼容 /chat/completions）
 *  - custom_responses : 自定义 Responses（OpenAI Responses API /responses）
 *  - deepseek       : DeepSeek 官方（https://api.deepseek.com）
 *  - openai         : OpenAI 官方（https://api.openai.com/v1）
 *  - free           : 兼容旧版内置免费接口
 *
 * 同时提供获取模型列表（GET /models）能力，供后台下拉选择。
 */
class AiProvider
{
    /** 提供商预设基础地址 */
    const PRESETS = array(
        'deepseek' => array(
            'label' => 'DeepSeek',
            'base'  => 'https://api.deepseek.com',
            'chat'  => 'https://api.deepseek.com/chat/completions',
            'models'=> 'https://api.deepseek.com/models',
        ),
        'openai' => array(
            'label' => 'OpenAI',
            'base'  => 'https://api.openai.com/v1',
            'chat'  => 'https://api.openai.com/v1/chat/completions',
            'responses' => 'https://api.openai.com/v1/responses',
            'models'=> 'https://api.openai.com/v1/models',
        ),
    );

    /** 提供商类型 */
    private $provider = 'custom_chat';
    /** 用户填写的地址（自定义时） */
    private $rawUrl = '';
    private $apiKey = '';
    private $model = '';
    private $timeout = 60;
    private $chatUrl = '';
    private $responsesUrl = '';
    private $modelsUrl = '';

    /**
     * 构造
     *
     * @param string $provider 提供商类型（custom_chat/custom_responses/deepseek/openai/free）
     * @param string $rawUrl   自定义地址（custom_* 必填；预设可留空自动使用官方地址）
     * @param string $apiKey   API 密钥
     * @param string $model    模型名
     * @param int    $timeout  超时秒数
     */
    public function __construct($provider = 'custom_chat', $rawUrl = '', $apiKey = '', $model = '', $timeout = 60)
    {
        $this->provider = $provider;
        $this->rawUrl = trim($rawUrl);
        $this->apiKey = trim($apiKey);
        $this->model = trim($model);
        $this->timeout = max(5, intval($timeout));

        // 解析预设地址
        $preset = isset(self::PRESETS[$provider]) ? self::PRESETS[$provider] : null;

        if ($provider === 'deepseek' || $provider === 'openai') {
            // 预设提供商：官方地址，用户只需填密钥和模型
            $this->chatUrl = $preset['chat'];
            if (isset($preset['responses'])) {
                $this->responsesUrl = $preset['responses'];
            }
            $this->modelsUrl = $preset['models'];
        } elseif ($provider === 'custom_chat') {
            // 自定义 Chat Completions
            $this->chatUrl = self::normalizeChatUrl($rawUrl);
            $this->modelsUrl = self::chatUrlToBase($this->chatUrl) . '/models';
        } elseif ($provider === 'custom_responses') {
            // 自定义 Responses API：地址为 base，自动拼 /responses 和 /models
            $base = self::normalizeBaseUrl($rawUrl);
            $this->responsesUrl = $base . '/responses';
            $this->modelsUrl = $base . '/models';
        } elseif ($provider === 'free') {
            // 旧版免费接口（向后兼容）
            if (class_exists('AiModeration')) {
                $this->chatUrl = AiModeration::normalizeApiUrl(AiModeration::FREE_API_URL);
                $this->apiKey = AiModeration::FREE_API_KEY;
                $this->model = AiModeration::FREE_API_MODEL;
                $this->modelsUrl = self::chatUrlToBase($this->chatUrl) . '/models';
            } else {
                // 兜底：直接使用内置免费配置（不依赖 AiModeration 类）
                $this->chatUrl = 'https://newapi.nki.pw/v1/chat/completions';
                $this->apiKey = 'sk-IZ5WDehg4A5P3XyNkZHdwxsPxFvMmIQP0m0dDkVOSwBsB0Dh';
                $this->model = '[福利]GPT-4o';
                $this->modelsUrl = 'https://newapi.nki.pw/v1/models';
            }
        }
    }

    /**
     * 从主题配置构造提供商实例（读取 aiWriter 相关字段，向后兼容）
     *
     * 优先读取新字段：aiWriterProvider（chat/responses/deepseek/openai/free）
     * 兼容旧字段：aiWriterApiType（free/custom）
     *
     * @return AiProvider
     */
    public static function fromOptions()
    {
        $options = \Typecho\Widget::widget('Widget_Options');

        $provider = isset($options->aiWriterProvider) ? $options->aiWriterProvider : '';
        if (empty($provider)) {
            // 兼容旧版：aiWriterApiType free/custom
            $apiType = isset($options->aiWriterApiType) ? $options->aiWriterApiType : 'custom';
            $provider = ($apiType === 'free') ? 'free' : 'custom_chat';
        }

        $url = isset($options->aiWriterApiUrl) ? $options->aiWriterApiUrl : '';
        $key = isset($options->aiWriterApiKey) ? $options->aiWriterApiKey : '';
        $model = isset($options->aiWriterModel) ? $options->aiWriterModel : 'gpt-3.5-turbo';
        $timeout = isset($options->aiWriterTimeout) ? intval($options->aiWriterTimeout) : 60;

        return new self($provider, $url, $key, $model, $timeout);
    }

    /**
     * 从主题配置构造统一接口实例（写作与审核共用）
     */
    public static function fromUnifiedOptions()
    {
        $options = \Typecho\Widget::widget('Widget_Options');

        $provider = isset($options->aiUnifiedProvider) ? $options->aiUnifiedProvider : '';
        if (empty($provider)) {
            $apiType = isset($options->aiUnifiedApiType) ? $options->aiUnifiedApiType : 'custom';
            $provider = ($apiType === 'free') ? 'free' : 'custom_chat';
        }

        $url = isset($options->aiModerationApiUrl) ? $options->aiModerationApiUrl : '';
        $key = isset($options->aiModerationApiKey) ? $options->aiModerationApiKey : '';
        $model = isset($options->aiModerationModel) ? $options->aiModerationModel : 'gpt-3.5-turbo';
        $timeout = isset($options->aiWriterTimeout) ? intval($options->aiWriterTimeout) : 60;

        return new self($provider, $url, $key, $model, $timeout);
    }

    /**
     * 从主题配置构造审核专用实例
     */
    public static function fromModerationOptions()
    {
        $options = \Typecho\Widget::widget('Widget_Options');

        $provider = isset($options->aiModerationProvider) ? $options->aiModerationProvider : '';
        if (empty($provider)) {
            $apiType = isset($options->aiModerationApiType) ? $options->aiModerationApiType : 'custom';
            $provider = ($apiType === 'free') ? 'free' : 'custom_chat';
        }

        $url = isset($options->aiModerationSepApiUrl) ? $options->aiModerationSepApiUrl : '';
        $key = isset($options->aiModerationSepApiKey) ? $options->aiModerationSepApiKey : '';
        $model = isset($options->aiModerationSepModel) ? $options->aiModerationSepModel : 'gpt-3.5-turbo';
        // 若分别设置未配置，回退统一字段
        if (empty($url) || empty($key)) {
            $url = isset($options->aiModerationApiUrl) ? $options->aiModerationApiUrl : '';
            $key = isset($options->aiModerationApiKey) ? $options->aiModerationApiKey : '';
            $model = isset($options->aiModerationModel) ? $options->aiModerationModel : 'gpt-3.5-turbo';
        }
        $timeout = isset($options->aiModerationTimeout) ? intval($options->aiModerationTimeout) : 30;

        return new self($provider, $url, $key, $model, $timeout);
    }

    /**
     * 是否已配置（预设提供商只需密钥；自定义需地址+密钥）
     */
    public function isConfigured()
    {
        if ($this->provider === 'deepseek' || $this->provider === 'openai') {
            return !empty($this->apiKey);
        }
        if ($this->provider === 'free') {
            return !empty($this->chatUrl) && !empty($this->apiKey);
        }
        return !empty($this->chatUrl) && !empty($this->apiKey);
    }

    /**
     * 获取提供商类型
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * 获取模型名
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * 获取 Chat 地址
     */
    public function getChatUrl()
    {
        return $this->chatUrl;
    }

    /**
     * 规范化 Chat Completions 地址
     */
    public static function normalizeChatUrl($url)
    {
        $url = trim($url);
        if (empty($url)) return '';
        $url = rtrim($url, '/');
        if (preg_match('#/chat/completions$#i', $url)) return $url;
        if (preg_match('#/v\d+$#i', $url)) return $url . '/chat/completions';
        if (preg_match('#/v\d+/#i', $url)) return $url . '/chat/completions';
        if (!preg_match('#/v\d+#i', $url)) return $url . '/v1/chat/completions';
        return $url . '/chat/completions';
    }

    /**
     * 规范化基础地址（去尾部斜杠，保留 /v1）
     */
    public static function normalizeBaseUrl($url)
    {
        $url = trim($url);
        if (empty($url)) return '';
        return rtrim($url, '/');
    }

    /**
     * 从 Chat 地址推导基础地址（去掉 /chat/completions）
     */
    public static function chatUrlToBase($chatUrl)
    {
        return preg_replace('#/chat/completions$#i', '', rtrim($chatUrl, '/'));
    }

    /**
     * 从 API 响应提取错误信息（兼容 OpenAI 格式）
     */
    public static function extractApiError($response)
    {
        $data = @json_decode($response, true);
        if (is_array($data) && isset($data['error'])) {
            $err = $data['error'];
            if (is_array($err)) {
                $msg = isset($err['message']) ? $err['message'] : '';
                if (isset($err['type'])) $msg = $err['type'] . ': ' . $msg;
                return $msg;
            }
            return (string)$err;
        }
        // 非 JSON，截取开头
        $trim = trim((string)$response);
        if ($trim !== '') {
            return mb_substr($trim, 0, 200);
        }
        return null;
    }

    /**
     * 调用 Chat Completions
     *
     * @param array  $messages     [{role, content}, ...]
     * @param float  $temperature
     * @param int    $maxTokens
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string, 'http_code'=>int]
     */
    public function chat($messages, $temperature = 0.7, $maxTokens = 2000)
    {
        if (empty($this->chatUrl)) {
            return array('success' => false, 'message' => 'AI接口未配置，请填写 API 地址和密钥');
        }
        if (empty($this->apiKey)) {
            return array('success' => false, 'message' => 'AI接口未配置，请填写 API 密钥');
        }

        $postData = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => floatval($temperature),
        );
        if ($maxTokens > 0) {
            $postData['max_tokens'] = intval($maxTokens);
        }

        return $this->httpPost($this->chatUrl, $postData, function ($result) {
            if (isset($result['choices'][0]['message']['content'])) {
                return $result['choices'][0]['message']['content'];
            }
            return null;
        });
    }

    /**
     * 调用 Responses API
     *
     * @param string $systemPrompt 系统提示
     * @param string $userPrompt   用户输入（支持数组）
     * @param float  $temperature
     * @param int    $maxTokens
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string, 'http_code'=>int]
     */
    public function responses($systemPrompt, $userPrompt, $temperature = 0.7, $maxTokens = 2000)
    {
        if (empty($this->responsesUrl)) {
            return array('success' => false, 'message' => '当前提供商不支持 Responses API，请改用 Chat Completions 或更换提供商');
        }
        if (empty($this->apiKey)) {
            return array('success' => false, 'message' => 'AI接口未配置，请填写 API 密钥');
        }

        $postData = array(
            'model' => $this->model,
            'temperature' => floatval($temperature),
        );
        if (!empty($systemPrompt)) {
            $postData['instructions'] = $systemPrompt;
        }
        if (is_string($userPrompt)) {
            $postData['input'] = $userPrompt;
        } else {
            $postData['input'] = $userPrompt;
        }
        if ($maxTokens > 0) {
            $postData['max_output_tokens'] = intval($maxTokens);
        }

        return $this->httpPost($this->responsesUrl, $postData, function ($result) {
            // Responses API 输出结构：output[].content[].text 或 output_text
            if (isset($result['output_text'])) {
                return $result['output_text'];
            }
            if (isset($result['output']) && is_array($result['output'])) {
                foreach ($result['output'] as $item) {
                    if (isset($item['content']) && is_array($item['content'])) {
                        foreach ($item['content'] as $c) {
                            if (isset($c['text'])) return $c['text'];
                        }
                    }
                }
            }
            return null;
        });
    }

    /**
     * 统一入口：根据提供商类型自动选择 Chat 或 Responses
     */
    public function complete($systemPrompt, $userPrompt, $temperature = 0.7, $maxTokens = 2000)
    {
        if ($this->provider === 'custom_responses' || $this->provider === 'openai') {
            // OpenAI 官方与自定义 Responses 优先使用 Responses API（若已配置）
            if (!empty($this->responsesUrl)) {
                return $this->responses($systemPrompt, $userPrompt, $temperature, $maxTokens);
            }
        }
        $messages = array();
        if (!empty($systemPrompt)) {
            $messages[] = array('role' => 'system', 'content' => $systemPrompt);
        }
        $messages[] = array('role' => 'user', 'content' => $userPrompt);
        return $this->chat($messages, $temperature, $maxTokens);
    }

    /**
     * 流式调用 Chat Completions（SSE）
     *
     * @param array   $messages   [{role, content}, ...]
     * @param float   $temperature
     * @param int     $maxTokens
     * @param callable $onChunk   收到增量文本时回调 function(string $delta)
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string, 'http_code'=>int]
     */
    public function chatStream($messages, $temperature = 0.7, $maxTokens = 2000, $onChunk = null)
    {
        if (empty($this->chatUrl)) {
            return array('success' => false, 'message' => 'AI接口未配置，请填写 API 地址和密钥');
        }
        if (empty($this->apiKey)) {
            return array('success' => false, 'message' => 'AI接口未配置，请填写 API 密钥');
        }

        $postData = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => floatval($temperature),
            'stream' => true,
        );
        if ($maxTokens > 0) {
            $postData['max_tokens'] = intval($maxTokens);
        }

        $content = '';
        $buffer = '';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->chatUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, max($this->timeout, 300));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$content, &$buffer, $onChunk) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $pos), "\r");
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if ($line === '') continue;
                if (strpos($line, 'data:') === 0) {
                    $payload = trim(substr($line, 5));
                    if ($payload === '[DONE]') continue;
                    $json = @json_decode($payload, true);
                    if (is_array($json) && isset($json['choices'][0]['delta']['content'])) {
                        $delta = (string)$json['choices'][0]['delta']['content'];
                        if ($delta !== '') {
                            $content .= $delta;
                            if (is_callable($onChunk)) {
                                $onChunk($delta);
                            }
                        }
                    }
                }
            }
            return strlen($data);
        });
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $hint = (stripos($error, 'SSL certificate') !== false)
                ? '（SSL证书问题，建议联系主机商修复CA证书）' : '';
            return array('success' => false, 'content' => $content, 'message' => '连接失败: ' . $error . $hint, 'http_code' => 0);
        }
        if ($httpCode !== 200) {
            $apiError = self::extractApiError($content);
            $detail = $apiError ? '：' . $apiError : '';
            $hint = '';
            if ($httpCode === 401) {
                $hint = ' — 请检查API密钥是否正确，或该密钥是否有权访问所选模型';
            } elseif ($httpCode === 404) {
                $hint = ' — 请检查API地址是否正确';
            }
            return array(
                'success' => false,
                'content' => $content,
                'message' => 'API返回错误 (HTTP ' . $httpCode . $detail . ')' . $hint,
                'http_code' => $httpCode,
            );
        }
        if ($content === '') {
            return array('success' => false, 'content' => '', 'message' => 'AI未返回有效内容', 'http_code' => $httpCode);
        }

        return array('success' => true, 'content' => $content, 'http_code' => $httpCode);
    }

    /**
     * 流式统一入口（AI 总结等场景）
     *
     * 仅 Chat Completions 类提供商支持流式；纯 Responses 提供商自动降级为非流式，
     * 将完整结果通过一次回调发出。
     *
     * @param string   $systemPrompt
     * @param string   $userPrompt
     * @param float    $temperature
     * @param int      $maxTokens
     * @param callable $onChunk   收到增量文本时回调 function(string $delta)
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string, 'http_code'=>int]
     */
    public function completeStream($systemPrompt, $userPrompt, $temperature = 0.7, $maxTokens = 2000, $onChunk = null)
    {
        if (empty($this->chatUrl)) {
            // 无 Chat 地址（纯 Responses 提供商）：降级为非流式
            $result = $this->complete($systemPrompt, $userPrompt, $temperature, $maxTokens);
            if ($result['success'] && is_callable($onChunk)) {
                $onChunk($result['content']);
            }
            return $result;
        }
        $messages = array();
        if (!empty($systemPrompt)) {
            $messages[] = array('role' => 'system', 'content' => $systemPrompt);
        }
        $messages[] = array('role' => 'user', 'content' => $userPrompt);
        return $this->chatStream($messages, $temperature, $maxTokens, $onChunk);
    }

    /**
     * 获取模型列表
     *
     * @return array ['success'=>bool, 'models'=>array, 'message'=>string]
     */
    public function fetchModels()
    {
        if (empty($this->modelsUrl)) {
            return array('success' => false, 'models' => array(), 'message' => '当前提供商不支持获取模型列表');
        }
        if (empty($this->apiKey)) {
            return array('success' => false, 'models' => array(), 'message' => '请先填写 API 密钥');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->modelsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return array('success' => false, 'models' => array(), 'message' => '连接失败: ' . $error);
        }
        if ($httpCode !== 200) {
            $apiError = self::extractApiError($response);
            $hint = '';
            if ($httpCode === 401) {
                $hint = ' — 请检查 API 密钥是否正确';
            } elseif ($httpCode === 404) {
                $hint = ' — 接口地址可能不支持模型列表';
            }
            return array(
                'success' => false,
                'models' => array(),
                'message' => '获取失败 (HTTP ' . $httpCode . ($apiError ? '：' . $apiError : '') . ')' . $hint,
            );
        }

        $data = json_decode($response, true);
        $models = array();
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $m) {
                if (is_array($m) && isset($m['id'])) {
                    $models[] = $m['id'];
                }
            }
        }
        if (empty($models)) {
            return array('success' => false, 'models' => array(), 'message' => '未解析到模型列表（接口返回格式不支持）');
        }
        sort($models);

        return array('success' => true, 'models' => $models, 'message' => '获取到 ' . count($models) . ' 个模型');
    }

    /**
     * 通用 POST 请求 + 解析
     */
    private function httpPost($url, $postData, $parseFn)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $hint = (stripos($error, 'SSL certificate') !== false)
                ? '（SSL证书问题，建议联系主机商修复CA证书）' : '';
            return array('success' => false, 'message' => '连接失败: ' . $error . $hint, 'http_code' => 0);
        }

        if ($httpCode !== 200) {
            $apiError = self::extractApiError($response);
            $detail = $apiError ? '：' . $apiError : '';
            $hint = '';
            if ($httpCode === 401) {
                $hint = ' — 请检查API密钥是否正确，或该密钥是否有权访问所选模型';
            } elseif ($httpCode === 404) {
                $hint = ' — 请检查API地址是否正确';
            } elseif ($httpCode === 503) {
                $hint = ' — 可能模型名错误或该模型暂无可用通道';
            }
            return array(
                'success' => false,
                'message' => 'API返回错误 (HTTP ' . $httpCode . $detail . ')' . $hint,
                'http_code' => $httpCode,
            );
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            return array('success' => false, 'message' => 'API响应解析失败', 'http_code' => $httpCode);
        }

        $content = $parseFn($result);
        if ($content === null) {
            return array('success' => false, 'message' => 'API响应格式异常，未找到文本内容', 'http_code' => $httpCode);
        }

        return array('success' => true, 'content' => (string)$content, 'http_code' => $httpCode);
    }
}
