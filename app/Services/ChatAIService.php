<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatAIService
{
    private $apiKey;
    private $apiUrl;
    private $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
        $this->model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->apiUrl = config('services.groq.url', 'https://api.groq.com/openai/v1/chat/completions');
    }

    /**
     * Generate AI response with intent detection
     */
    public function generateAIResponse($message, $trackingInfo, $chatSessionId, $isExternal = false, $conversation = [], $lang = 'ar')
    {
        try {
            Log::info('ChatAIService: Starting request', [
                'message' => $message,
                'has_tracking' => isset($trackingInfo['tracking_no']),
                'is_external' => $isExternal,
                'lang' => $lang
            ]);

            // 1. Detect user intent
            $intent = $this->detectIntent($message, $lang);
            Log::info('Intent detected:', ['intent' => $intent]);

            // 2. Build appropriate prompt based on intent
            $prompt = $this->buildIntentBasedPrompt($intent, $message, $trackingInfo, $isExternal, $conversation, $lang);

            // 3. Call Groq API
            $certPath = public_path('certs/cacert.pem');
            $httpClient = file_exists($certPath)
                ? Http::withOptions(['verify' => $certPath])->timeout(30)
                : Http::timeout(30);

            $response = $httpClient
                ->withToken($this->apiKey)
                ->post($this->apiUrl, [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->getSystemPrompt($lang)
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 200, // Keep responses short
                ]);

            Log::info('ChatAIService: Response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $aiData = $response->json();
                $rawText = $aiData['choices'][0]['message']['content'] ?? '';
                return $this->processAIResponse($rawText, $chatSessionId, $intent, $lang);
            } else {
                Log::error('Groq API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->getFallbackResponse($intent, $lang, $chatSessionId);
            }
        } catch (\Exception $e) {
            Log::error('ChatAIService Exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->getFallbackResponse('general', $lang, $chatSessionId);
        }
    }

    /**
     * Detect user intent from message
     */
    private function detectIntent($message, $lang): string
    {
        $message = mb_strtolower(trim($message), 'UTF-8');

        $keywords = [
            'tracking' => ['تتبع', 'شحنة', 'طرد', 'طلب', 'اين', 'وين', 'وصل', 'track', 'shipment', 'where', 'status', 'PE', 'parcel'],
            'complaint' => ['شكوى', 'مشكلة', 'خطأ', 'متأخر', 'تأخير', 'زعلان', 'complaint', 'problem', 'issue', 'delayed', 'late', 'upset'],
            'delivery' => ['توصيل', 'موعد', 'متى', 'امتى', 'تسليم', 'delivery', 'arrival', 'when', 'time', 'eta'],
            'fees' => ['رسوم', 'تكلفة', 'سعر', 'دفع', 'كم', 'فلوس', 'fees', 'cost', 'price', 'payment', 'how much', 'charge'],
            'contact' => ['اتصال', 'تحدث', 'تكلم', 'مندوب', 'موظف', 'انسان', 'بشر', 'contact', 'talk', 'speak', 'agent', 'human', 'representative'],
            'greeting' => ['مرحبا', 'السلام', 'اهلا', 'هلا', 'هلو', 'صباح', 'مساء', 'hi', 'hello', 'hey', 'good morning', 'good evening']
        ];

        foreach ($keywords as $intent => $wordList) {
            foreach ($wordList as $word) {
                if (mb_strpos($message, $word) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * Get system prompt based on language
     */
    private function getSystemPrompt($lang): string
    {
        if ($lang === 'ar') {
            return "أنت مساعد ذكي لشركة Parcel Express للشحن في سلطنة عُمان. قواعدك:
1. تكلم باللهجة العُمانية بشكل طبيعي وودي
2. استخدم كلمات عُمانية مثل: (شلون، وين، باش، زين، تبي، حق، لين، هني، وايد)
3. أجب بـ ٢-٣ جمل فقط (حد أقصى ٤٠ كلمة)
4. استخدم الأرقام العربية (١، ٢، ٣) وليس الإنجليزية
5. استخدم إيموجي واحد مناسب فقط
6. كن دقيقاً في المعلومات
7. لا تكرر المعلومات المذكورة
8. تحدث كموظف عُماني بشري وليس روبوت
9. معلومات الشركة:
   - الموقع: https://parcelexpress.om/
   - رقم التواصل: ٧٢٢٢ ٦٢٨٨
   - الرسوم: تبدأ من ٢ ريال عُماني حسب المنطقة";
        } else {
            return "You are a smart assistant for Parcel Express shipping company in Oman. Your rules:
1. Be friendly and professional with natural tone
2. Answer in 2-3 sentences only (max 40 words)
3. Use English numbers (1, 2, 3)
4. Use only one appropriate emoji
5. Be accurate with information
6. Don't repeat information
7. Speak like a human agent in Oman, not a robot
8. Company information:
   - Website: https://parcelexpress.om/
   - Contact: 7222 6288
   - Fees: Start from 2 OMR depending on area";
        }
    }

    /**
     * Build intent-based prompt
     */
    private function buildIntentBasedPrompt($intent, $customerMessage, $trackingInfo, $isExternal, $conversation, $lang): string
    {
        $conversationText = $this->buildConversationContext($conversation, $lang);
        $trackingData = $this->extractTrackingData($trackingInfo);
        $trackingDetails = $this->formatTrackingDetails($trackingData, $isExternal, $lang);

        $intentInstructions = $this->getIntentInstructions($intent, $lang);

        if ($lang === 'ar') {
            return "### سياق المحادثة:
{$conversationText}

### رسالة العميل:
\"{$customerMessage}\"

### معلومات الشحنة:
{$trackingDetails}

### التعليمات:
{$intentInstructions}

### قواعد الرد:
- ٢-٣ جمل فقط (٤٠ كلمة كحد أقصى)
- تكلم باللهجة العُمانية (استخدم: شلون، وين، باش، زين، تبي، حق، هني، وايد)
- استخدم الأرقام العربية (٧٢٢٢ ٦٢٨٨) وليس الإنجليزية
- إيموجي واحد فقط في البداية
- لا تكرر المعلومات الموجودة بالأعلى
- تحدث كموظف عُماني وليس روبوت
- اختم بجملة مفيدة أو سؤال بسيط";
        } else {
            return "### Conversation Context:
{$conversationText}

### Customer Message:
\"{$customerMessage}\"

### Shipment Information:
{$trackingDetails}

### Instructions:
{$intentInstructions}

### Response Rules:
- 2-3 sentences only (40 words max)
- Use natural friendly tone for Oman
- Use English numbers (7222 6288)
- One emoji at the start only
- Don't repeat information above
- Speak like a human agent in Oman, not a robot
- End with helpful statement or simple question";
        }
    }

    /**
     * Extract tracking data safely
     */
    private function extractTrackingData($trackingInfo)
    {
        if (is_object($trackingInfo) && method_exists($trackingInfo, 'toArray')) {
            $trackingInfo = $trackingInfo->toArray();
        }

        if (is_array($trackingInfo) && isset($trackingInfo['data'])) {
            return $trackingInfo['data'];
        }

        return $trackingInfo;
    }

    /**
     * Format tracking details concisely
     */
    private function formatTrackingDetails($trackingData, $isExternal, $lang): string
    {
        if (!isset($trackingData['tracking_no']) || $trackingData['tracking_no'] === 'N/A') {
            return $lang === 'ar' ? 'ما فيه معلومات طرد' : 'No shipment information';
        }

        $details = [];
        
        if ($lang === 'ar') {
            $details[] = 'رقم التتبع: ' . $this->convertToArabicNumbers($trackingData['tracking_no'] ?? 'N/A');
        } else {
            $details[] = 'Tracking: ' . ($trackingData['tracking_no'] ?? 'N/A');
        }

        if ($isExternal) {
            $details[] = ($lang === 'ar' ? 'طرد دولي عبر GFS' : 'International shipment via GFS');
            $details[] = ($lang === 'ar' ? 'من: ' : 'From: ') . ($trackingData['from_country'] ?? 'N/A');
            $details[] = ($lang === 'ar' ? 'إلى: ' : 'To: ') . ($trackingData['to_country'] ?? 'N/A');
        } else {
            if (isset($trackingData['consignee']['name'])) {
                $details[] = ($lang === 'ar' ? 'المستلم: ' : 'Consignee: ') . $trackingData['consignee']['name'];
            }
            if (isset($trackingData['consignee']['state']['en_name'])) {
                $details[] = ($lang === 'ar' ? 'المدينة: ' : 'City: ') . $trackingData['consignee']['state']['en_name'];
            }
        }

        // Latest status from history
        if (isset($trackingData['shipment_histories']) && is_array($trackingData['shipment_histories']) && !empty($trackingData['shipment_histories'])) {
            $latest = $trackingData['shipment_histories'][0];
            $statusText = $isExternal ? ($latest['message'] ?? $latest['name']) : $latest['name'];
            $details[] = ($lang === 'ar' ? 'آخر حالة: ' : 'Latest: ') . $statusText;
            $details[] = ($lang === 'ar' ? 'التوقيت: ' : 'Time: ') . ($latest['time'] ?? 'N/A');
        }

        return implode("\n", $details);
    }

    /**
     * Convert English numbers to Arabic numbers
     */
    private function convertToArabicNumbers($text): string
    {
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return str_replace($englishNumbers, $arabicNumbers, $text);
    }

    /**
     * Get intent-specific instructions
     */
    private function getIntentInstructions($intent, $lang): string
    {
        $instructions = [
            'ar' => [
                'tracking' => 'قدم ملخص سريع للحالة الحالية وآخر تحديث فقط. استخدم اللهجة العُمانية.',
                'complaint' => 'اعترف بمشاعر العميل واعتذر بلهجة عُمانية. وجهه لتقديم تذكرة على الموقع: https://parcelexpress.om/',
                'delivery' => 'أخبر العميل عن آخر حالة والموقع الحالي بشكل مختصر باللهجة العُمانية.',
                'fees' => 'اذكر أن الرسوم تبدأ من ٢ ريال عُماني حسب المنطقة في سلطنة عُمان. استخدم الأرقام العربية.',
                'contact' => 'أعطه رقم التواصل: ٧٢٢٢ ٦٢٨٨ واعرض عليه المساعدة. استخدم اللهجة العُمانية.',
                'greeting' => 'رحب بالعميل بود باللهجة العُمانية واعرض عليه خيارات المساعدة الرئيسية.',
                'general' => 'أجب على سؤال العميل مباشرة وبدقة باللهجة العُمانية. إن لم يكن واضحاً، اطلب توضيح.',
            ],
            'en' => [
                'tracking' => 'Provide quick summary of current status and latest update only.',
                'complaint' => 'Acknowledge customer feelings and apologize. Direct them to submit a ticket on website: https://parcelexpress.om/',
                'delivery' => 'Tell customer about latest status and current location briefly.',
                'fees' => 'Mention fees start from 2 OMR depending on area in Oman.',
                'contact' => 'Give contact number: 7222 6288 and offer to help.',
                'greeting' => 'Welcome customer warmly and offer main help options.',
                'general' => 'Answer customer question directly and accurately. If unclear, ask for clarification.',
            ]
        ];

        return $instructions[$lang][$intent] ?? $instructions[$lang]['general'];
    }

    /**
     * Build conversation context
     */
    private function buildConversationContext($conversation, $lang = 'ar')
    {
        if (empty($conversation)) {
            return $lang === 'ar' ? 'ما فيه سياق سابق' : 'No previous context';
        }

        $lines = [];
        $maxMessages = 3; // Only last 3 messages for context
        $recentConversation = array_slice($conversation, -$maxMessages);

        foreach ($recentConversation as $item) {
            $who = $item['from'] === 'customer'
                ? ($lang === 'ar' ? 'العميل' : 'Customer')
                : ($lang === 'ar' ? 'المساعد' : 'Assistant');
            $lines[] = "{$who}: " . $item['message'];
        }

        return implode("\n", $lines);
    }

    /**
     * Process AI response
     */
    private function processAIResponse($aiResponse, $chatSessionId, $intent, $lang)
    {
        $formattedMessage = $this->formatForWhatsApp($aiResponse);

        // Add menu hint for greetings
        if ($intent === 'greeting') {
            $menuHint = $lang === 'ar'
                ? "\n\n💡 _أرسل \"قائمة\" باش تشوف الخيارات المتاحة_"
                : "\n\n💡 _Send \"menu\" to see available options_";
            $formattedMessage .= $menuHint;
        }

        return [
            'message' => $formattedMessage,
            'chatSessionId' => $chatSessionId,
            'formattedMessage' => $formattedMessage,
            'detectedIntent' => $intent
        ];
    }

    /**
     * Format text for WhatsApp
     */
    private function formatForWhatsApp($text)
    {
        $text = strip_tags($text);
        $text = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $text);
        $text = str_replace('\\n', "\n", $text);
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        $text = html_entity_decode($text);

        return trim($text);
    }

    /**
     * Fallback response based on intent
     */
    private function getFallbackResponse($intent, $lang, $chatSessionId)
    {
        $responses = [
            'ar' => [
                'tracking' => "📦 من فضلك أرسل رقم التتبع باش أساعدك تعرف حالة طردك.",
                'complaint' => "🙏 نعتذر على الإزعاج. لو تبي تقدم شكوى رسمية، سو تذكرة من الموقع:\n🌐 https://parcelexpress.om/\n\nأو اكتب المشكلة هني وبنساعدك!",
                'delivery' => "🚚 أرسل رقم التتبع باش أخبرك موعد التوصيل المتوقع.",
                'fees' => "💰 رسوم التوصيل في عُمان تبدأ من ٢ ريال عُماني حسب المنطقة.\n\nأرسل رقم التتبع لمعرفة السعر الدقيق.",
                'contact' => "📞 شلون أقدر أساعدك اليوم؟\n\nللتواصل المباشر: ٧٢٢٢ ٦٢٨٨",
                'greeting' => "👋 مرحبا فيك في Parcel Express!\n\n📋 أرسل \"قائمة\" باش تشوف الخيارات",
                'general' => "❓ شلون أقدر أساعدك؟ أرسل \"قائمة\" لعرض الخيارات المتاحة.",
            ],
            'en' => [
                'tracking' => "📦 Please send your tracking number to check your shipment status.",
                'complaint' => "🙏 We apologize for the inconvenience. To submit an official complaint, create a ticket on our website:\n🌐 https://parcelexpress.om/\n\nOr write your issue here and we'll help!",
                'delivery' => "🚚 Send tracking number to know estimated delivery time.",
                'fees' => "💰 Delivery fees in Oman start from 2 OMR depending on area.\n\nSend tracking number for exact price.",
                'contact' => "📞 How can I help you today?\n\nFor direct contact: 7222 6288",
                'greeting' => "👋 Welcome to Parcel Express!\n\n📋 Send \"menu\" to see options",
                'general' => "❓ How can I help you? Send \"menu\" to see available options.",
            ]
        ];

        $message = $responses[$lang][$intent] ?? $responses[$lang]['general'];

        return [
            'message' => $message,
            'chatSessionId' => $chatSessionId,
            'formattedMessage' => $message,
            'detectedIntent' => $intent
        ];
    }
}