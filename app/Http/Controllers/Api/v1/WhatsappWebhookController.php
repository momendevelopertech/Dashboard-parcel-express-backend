<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\ChatAIService;
use App\Services\TrackingGFSService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Shipment;
use App\Models\Setting;

class WhatsappWebhookController extends Controller
{
    protected $chatAIService;
    protected $trackingGFSService;
    protected $whatsAppService;

    public function __construct(
        ChatAIService $chatAIService,
        TrackingGFSService $trackingGFSService,
        WhatsAppService $whatsAppService
    ) {
        $this->chatAIService = $chatAIService;
        $this->trackingGFSService = $trackingGFSService;
        $this->whatsAppService = $whatsAppService;
    }

    public function handle(Request $request)
    {
        Log::info('=== Ultramsg Webhook START ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl()
        ]);

        // Check auto-reply setting
        $autoReplySetting = Setting::where('key', 'enable_autoreply_from_whatsapp')->value('value');
        if ($autoReplySetting === 'no') {
            Log::info('WhatsApp auto-reply is disabled by setting.');
            return response()->json(['status' => 'disabled'], 200);
        }

        $messageData = $request->input('data');
        if (empty($messageData) || !isset($messageData['from'])) {
            Log::warning('No message data or missing from field');
            return response()->json(['status' => 'ok', 'message' => 'No message data.'], 200);
        }

        $senderPhone = $messageData['from'];
        $customerMessage = trim($messageData['body'] ?? '');

        Log::info('Processing message:', [
            'phone' => $senderPhone,
            'message' => substr($customerMessage, 0, 100),
            'length' => strlen($customerMessage)
        ]);

        if (empty($customerMessage)) {
            Log::warning('Empty message ignored');
            return response()->json(['status' => 'ok'], 200);
        }

        // Detect language
        $lang = $this->detectLanguage($customerMessage);
        Log::info('Language detected:', ['lang' => $lang]);

        // Get conversation context
        $conversationKey = "whatsapp_conversation_" . $senderPhone;
        $conversation = Cache::get($conversationKey, []);

        // Check if last activity was more than 5 minutes ago - reset with menu
        $lastActivityKey = "whatsapp_last_activity_" . $senderPhone;
        $lastActivity = Cache::get($lastActivityKey);
        
        if ($lastActivity && now()->diffInMinutes($lastActivity) >= 5) {
            Log::info('5 minutes inactivity detected - resetting conversation and sending menu');
            Cache::forget($conversationKey);
            $conversation = [];
            $this->sendWelcomeWithMenu($senderPhone, $lang);
            Cache::put($lastActivityKey, now(), now()->addMinutes(10));
            return response()->json(['status' => 'ok', 'message' => 'Menu resent after inactivity.'], 200);
        }
        
        // Update last activity timestamp
        Cache::put($lastActivityKey, now(), now()->addMinutes(10));

        // Check for menu request
        if ($this->isMenuRequest($customerMessage, $lang)) {
            Log::info('Menu request detected');
            $this->sendInteractiveMenu($senderPhone, $lang);
            return response()->json(['status' => 'ok', 'message' => 'Menu sent.'], 200);
        }

        // Check for menu selection
        if ($this->isMenuSelection($customerMessage, $lang)) {
            Log::info('Menu selection detected:', ['selection' => $customerMessage]);
            $conversation[] = ['from' => 'customer', 'message' => $customerMessage, 'timestamp' => now()];
            Cache::put($conversationKey, $conversation, now()->addMinutes(5));
            return $this->handleMenuSelection($senderPhone, $customerMessage, $lang, $conversation);
        }

        // Add customer message to conversation
        $conversation[] = ['from' => 'customer', 'message' => $customerMessage, 'timestamp' => now()];

        // Extract tracking number
        $trackingNumber = $this->extractTrackingNumber($customerMessage);

        if ($trackingNumber) {
            Log::info('Tracking number found:', ['tracking_number' => $trackingNumber]);
            Cache::put($conversationKey, $conversation, now()->addMinutes(5));
            return $this->handleTrackingRequest($senderPhone, $trackingNumber, $customerMessage, $conversation, $lang);
        }

        // Initial greeting - send welcome with menu
        if ($this->isInitialGreeting($customerMessage)) {
            Log::info('Initial greeting detected');
            Cache::put($conversationKey, $conversation, now()->addMinutes(5));
            $this->sendWelcomeWithMenu($senderPhone, $lang);
            return response()->json(['status' => 'ok', 'message' => 'Welcome sent.'], 200);
        }

        // Process general inquiry with AI
        Log::info('Processing general inquiry via AI');
        try {
            $dummyTrackingInfo = ['tracking_no' => 'N/A'];
            
            $aiResponse = $this->chatAIService->generateAIResponse(
                $customerMessage,
                $dummyTrackingInfo,
                'webhook-session',
                false,
                $conversation,
                $lang
            );

            $this->whatsAppService->sendMessage($senderPhone, $aiResponse['message']);
            Log::info('AI response sent successfully');

            $conversation[] = ['from' => 'assistant', 'message' => $aiResponse['message'], 'timestamp' => now()];
        } catch (\Exception $e) {
            Log::error('AI processing failed:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            $this->sendErrorMessage($senderPhone, $lang);
        }

        Cache::put($conversationKey, $conversation, now()->addMinutes(5));
        Log::info('=== Ultramsg Webhook END ===');
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Handle tracking request for internal or external shipments
     */
    private function handleTrackingRequest(string $senderPhone, string $trackingNumber, string $customerMessage, array $conversation, string $lang)
    {
        $conversationKey = "whatsapp_conversation_" . $senderPhone;
        $trackingInfo = null;
        $isExternal = false;

        Log::info('Processing tracking request', [
            'phone' => $senderPhone,
            'tracking_number' => $trackingNumber
        ]);

        // Check internal database first
        $shipment = Shipment::with(['shipmentHistories'])->where('tracking_no', $trackingNumber)->first();

        if ($shipment) {
            Log::info('Internal shipment found', [
                'tracking_no' => $trackingNumber,
                'status' => $shipment->status
            ]);

            // Check if already delivered
            if (strtolower($shipment->status) === "delivered") {
                Log::info('Shipment already delivered');
                $this->sendDeliveredMessage($senderPhone, $trackingNumber, $lang);
                return response()->json(['status' => 'ok', 'message' => 'Delivered.'], 200);
            }

            $trackingInfo = $shipment;
        } else {
            // Check GFS external tracking
            Log::info('Internal shipment not found, checking GFS...');

            $gfsResponse = $this->trackingGFSService->track(json_encode([
                'waybillNo' => $trackingNumber,
                'lan' => $lang === 'ar' ? 'ar' : 'en'
            ]));

            Log::info('GFS tracking response received', [
                'has_data' => !empty($gfsResponse),
                'is_external' => $gfsResponse['is_external'] ?? false
            ]);

            if ($gfsResponse && isset($gfsResponse['is_external']) && $gfsResponse['is_external']) {
                $gfsStatus = strtolower($gfsResponse['status'] ?? '');
                Log::info('GFS external tracking found', ['status' => $gfsStatus]);

                // Check if delivered
                if ($gfsStatus === 'delivered') {
                    Log::info('GFS shipment already delivered');
                    $this->sendDeliveredMessage($senderPhone, $trackingNumber, $lang);
                    return response()->json(['status' => 'ok', 'message' => 'Delivered.'], 200);
                }

                $trackingInfo = $gfsResponse;
                $isExternal = true;
            } else {
                // Not found in both systems
                Log::warning('Tracking number not found', ['tracking_no' => $trackingNumber]);
                $this->sendTrackingNotFoundResponse($senderPhone, $trackingNumber, $lang);
                return response()->json(['status' => 'ok', 'message' => 'Not found.'], 200);
            }
        }

        // Generate AI response for tracking
        try {
            Log::info('Calling AI Service for tracking inquiry...');

            $aiResponse = $this->chatAIService->generateAIResponse(
                $customerMessage,
                $trackingInfo,
                'webhook-session',
                $isExternal,
                $conversation,
                $lang
            );

            $this->whatsAppService->sendMessage($senderPhone, $aiResponse['message']);
            Log::info('AI tracking response sent successfully');

            $conversation[] = ['from' => 'assistant', 'message' => $aiResponse['message'], 'timestamp' => now()];
        } catch (\Exception $e) {
            Log::error('Failed to process AI tracking response:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            $this->sendErrorMessage($senderPhone, $lang);
        }

        Cache::put($conversationKey, $conversation, now()->addMinutes(5));
        return response()->json(['status' => 'ok', 'message' => 'Processed.'], 200);
    }

    /**
     * Check if message is a menu request
     */
    private function isMenuRequest(string $message, string $lang): bool
    {
        $message = mb_strtolower(trim($message), 'UTF-8');
        $menuKeywords = ['قائمة', 'القائمة', 'خيارات', 'مساعدة', 'menu', 'options', 'help'];

        foreach ($menuKeywords as $keyword) {
            if ($message === $keyword || mb_strpos($message, $keyword) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is a menu selection
     */
    private function isMenuSelection(string $message, string $lang): bool
    {
        $message = mb_strtolower(trim($message), 'UTF-8');
        
        // Check for numbers 1-6 (Arabic and English)
        if (preg_match('/^[1-6١-٦]$/', $message)) {
            return true;
        }

        // Check for emoji-based selections
        $emojis = ['📍', '📞', '📝', '💰', '🕐', 'ℹ️'];
        foreach ($emojis as $emoji) {
            if (mb_strpos($message, $emoji) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send welcome message with interactive menu
     */
    private function sendWelcomeWithMenu(string $phone, string $lang): void
    {
        if ($lang === 'ar') {
            $welcome = "👋 *مرحبا فيك في Parcel Express!*\n\n";
            $welcome .= "إحنا هني لمساعدتك على مدار الساعة 🌟\n\n";
            $welcome .= "شلون أقدر أساعدك اليوم؟";
        } else {
            $welcome = "👋 *Welcome to Parcel Express!*\n\n";
            $welcome .= "We're here to help you 24/7 🌟\n\n";
            $welcome .= "How can I assist you today?";
        }

        $this->whatsAppService->sendMessage($phone, $welcome);
        
        // Send menu after 1 second
        sleep(1);
        $this->sendInteractiveMenu($phone, $lang);
    }

    /**
     * Send interactive menu to user
     */
    private function sendInteractiveMenu(string $phone, string $lang): void
    {
        if ($lang === 'ar') {
            $menu = "📋 *القائمة الرئيسية*\n\n";
            $menu .= "اختار اللي تبيه:\n\n";
            $menu .= "١️⃣ 📍 *تتبع الطرد*\n";
            $menu .= "   تعرف وين طردك وشلون حالته\n\n";
            $menu .= "٢️⃣ 📞 *التواصل مع خدمة العملاء*\n";
            $menu .= "   للاستفسارات المستعجلة\n\n";
            $menu .= "٣️⃣ 📝 *تقديم شكوى*\n";
            $menu .= "   نسمع ملاحظاتك\n\n";
            $menu .= "٤️⃣ 💰 *الاستفسار عن الرسوم*\n";
            $menu .= "   تعرف كم تكلفة التوصيل\n\n";
            $menu .= "٥️⃣ 🕐 *مواعيد التوصيل*\n";
            $menu .= "   تعرف الوقت المتوقع\n\n";
            $menu .= "٦️⃣ ℹ️ *معلومات عامة*\n";
            $menu .= "   عن خدماتنا وأسئلة شايعة\n\n";
            $menu .= "━━━━━━━━━━━━━━━━━━\n";
            $menu .= "💡 _أرسل رقم الخيار أو اكتب سؤالك مباشرة_";
        } else {
            $menu = "📋 *Main Menu*\n\n";
            $menu .= "Choose what you need:\n\n";
            $menu .= "1️⃣ 📍 *Track Shipment*\n";
            $menu .= "   Check your parcel location and status\n\n";
            $menu .= "2️⃣ 📞 *Contact Customer Service*\n";
            $menu .= "   For urgent inquiries\n\n";
            $menu .= "3️⃣ 📝 *File a Complaint*\n";
            $menu .= "   We listen to your feedback\n\n";
            $menu .= "4️⃣ 💰 *Fees Inquiry*\n";
            $menu .= "   Know shipping costs\n\n";
            $menu .= "5️⃣ 🕐 *Delivery Times*\n";
            $menu .= "   Know expected delivery time\n\n";
            $menu .= "6️⃣ ℹ️ *General Information*\n";
            $menu .= "   About our services and FAQs\n\n";
            $menu .= "━━━━━━━━━━━━━━━━━━\n";
            $menu .= "💡 _Send option number or ask directly_";
        }

        $this->whatsAppService->sendMessage($phone, $menu);
        Log::info('Interactive menu sent', ['phone' => $phone, 'lang' => $lang]);
    }

    /**
     * Handle menu selection from user
     */
    private function handleMenuSelection(string $phone, string $selection, string $lang, array $conversation)
    {
        $selection = mb_strtolower(trim($selection), 'UTF-8');
        $firstChar = mb_substr($selection, 0, 1, 'UTF-8');
        
        // Convert Arabic numbers to English
        $arabicToEnglish = ['١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6'];
        if (isset($arabicToEnglish[$firstChar])) {
            $firstChar = $arabicToEnglish[$firstChar];
        }

        Log::info('Handling menu selection', [
            'phone' => $phone,
            'selection' => $selection,
            'first_char' => $firstChar
        ]);

        $response = '';

        switch ($firstChar) {
            case '1':
            case '📍':
                $response = $lang === 'ar'
                    ? "📦 *تتبع الطرد*\n\nزين! من فضلك أرسل رقم التتبع حق طردك.\n\n💡 _عادة يبدأ بـ PE وبعدين أرقام_"
                    : "📦 *Track Shipment*\n\nGreat! Please send your tracking number.\n\n💡 _Usually starts with PE followed by numbers_";
                break;

            case '2':
            case '📞':
                $response = $lang === 'ar'
                    ? "📞 *التواصل مع خدمة العملاء*\n\n🕐 إحنا متاحين من السبت إلى الخميس\n⏰ من ٩ الصبح لين ٦ المسا\n\n📱 للتواصل المباشر:\nاتصل: ٧٢٢٢ ٦٢٨٨\nواتساب: ٧٢٢٢ ٦٢٨٨\n\n💬 أو اكتب استفسارك هني وبنساعدك!"
                    : "📞 *Contact Customer Service*\n\n🕐 Available Saturday to Thursday\n⏰ 9 AM to 6 PM\n\n📱 For immediate contact:\nCall: 7222 6288\nWhatsApp: 7222 6288\n\n💬 Or write your inquiry here!";
                break;

            case '3':
            case '📝':
                $response = $lang === 'ar'
                    ? "📝 *تقديم شكوى*\n\nنعتذر على الإزعاج! 🙏\n\nلو تبي تقدم شكوى رسمية، تقدر تسوي تذكرة من خلال موقعنا:\n\n🌐 https://parcelexpress.om/\n\nوبنتواصل معاك في أسرع وقت.\n\nأو لو تبي تكتب الشكوى هني، أكتب وإحنا بنساعدك!"
                    : "📝 *File a Complaint*\n\nWe apologize for any inconvenience! 🙏\n\nIf you want to submit an official complaint, you can create a ticket through our website:\n\n🌐 https://parcelexpress.om/\n\nWe'll contact you ASAP.\n\nOr if you want to write your complaint here, go ahead and we'll help you!";
                break;

            case '4':
            case '💰':
                $response = $lang === 'ar'
                    ? "💰 *الاستفسار عن الرسوم*\n\nرسوم التوصيل في سلطنة عُمان تختلف حسب المنطقة:\n\n📍 الأسعار تبدأ من ٢ ريال عُماني\n\nلو تبي تعرف السعر الدقيق لطردك:\n📦 أرسل رقم التتبع\nأو\n📍 قول لي المدينة والوزن التقريبي"
                    : "💰 *Fees Inquiry*\n\nDelivery fees in Oman vary by area:\n\n📍 Prices start from 2 OMR\n\nTo know the exact price for your parcel:\n📦 Send tracking number\nor\n📍 Tell me the city and approximate weight";
                break;

            case '5':
            case '🕐':
                $response = $lang === 'ar'
                    ? "🕐 *مواعيد التوصيل*\n\nباش تعرف موعد توصيل طردك:\n\n📦 أرسل رقم التتبع الحين\n\n⏱️ عادة:\n• داخل نفس المنطقة: يوم - يومين\n• بين المناطق: ٢-٤ أيام\n• الشحن الدولي: ٥-١٠ أيام"
                    : "🕐 *Delivery Times*\n\nTo know your delivery time:\n\n📦 Send tracking number now\n\n⏱️ Usually:\n• Within same area: 1-2 days\n• Between areas: 2-4 days\n• International: 5-10 days";
                break;

            case '6':
            case 'ℹ':
                $response = $lang === 'ar'
                    ? "ℹ️ *معلومات عامة*\n\n🚚 *خدماتنا:*\n• شحن محلي ودولي\n• توصيل سريع express\n• تتبع مباشر للطرود\n• خدمة عملاء ٢٤/٧\n\n📋 *أسئلة شايعة:*\nأرسل \"أسئلة\" للمزيد\n\n🌐 *موقعنا:*\nhttps://parcelexpress.om/\n\n💬 شلون أقدر أساعدك؟"
                    : "ℹ️ *General Information*\n\n🚚 *Our Services:*\n• Local & International shipping\n• Express delivery\n• Real-time tracking\n• 24/7 customer service\n\n📋 *FAQs:*\nSend \"faq\" for more\n\n🌐 *Website:*\nhttps://parcelexpress.om/\n\n💬 How can I help you?";
                break;

            default:
                // If selection not recognized, process as general inquiry
                Log::info('Menu selection not recognized, processing as general inquiry');
                try {
                    $aiResponse = $this->chatAIService->generateAIResponse(
                        $selection,
                        ['tracking_no' => 'N/A'],
                        'webhook-session',
                        false,
                        $conversation,
                        $lang
                    );
                    $response = $aiResponse['message'];
                } catch (\Exception $e) {
                    Log::error('AI processing failed in menu selection', ['error' => $e->getMessage()]);
                    $this->sendErrorMessage($phone, $lang);
                    return response()->json(['status' => 'ok'], 200);
                }
        }

        $this->whatsAppService->sendMessage($phone, $response);
        Log::info('Menu selection response sent', ['phone' => $phone, 'option' => $firstChar]);

        return response()->json(['status' => 'ok', 'message' => 'Menu selection processed.'], 200);
    }

    /**
     * Detect language from message
     */
    private function detectLanguage(string $message): string
    {
        // Check for Arabic characters
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message)) {
            return 'ar';
        }
        
        // Check for Arabic words (including Omani dialect)
        $arabicWords = ['مرحبا', 'السلام', 'اهلا', 'هلا', 'صباح', 'مساء', 'شحنة', 'طرد', 'تتبع', 'طلب', 'رقم', 
                        'شلون', 'وين', 'كيف', 'ليش', 'باش', 'زين', 'تبي', 'تبيه', 'حق', 'لين', 'هني'];
        foreach ($arabicWords as $word) {
            if (mb_stripos($message, $word) !== false) {
                return 'ar';
            }
        }
        
        return 'en';
    }

    /**
     * Check if message is initial greeting
     */
    private function isInitialGreeting(string $message): bool
    {
        $greetings = [
            // Arabic greetings (including Omani)
            'مرحبا', 'السلام عليكم', 'سلام', 'اهلا', 'اهلين', 'هلا', 'هلو',
            'صباح الخير', 'مساء الخير', 'السلام', 'مرحبتين', 'هلا والله',
            // English greetings
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'greetings'
        ];

        $message = mb_strtolower(trim($message), 'UTF-8');

        foreach ($greetings as $greeting) {
            if ($message === $greeting || mb_stripos($message, $greeting) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract tracking number from message
     */
    private function extractTrackingNumber(string $message): ?string
    {
        $patterns = [
            '/\bPE[A-Z0-9]{6,}\b/i',         // PE followed by 6+ chars
            '/\b[A-Z]{2,3}[0-9]{8,}\b/i',    // 2-3 letters + 8+ digits
            '/\b[0-9]{10,20}\b/',             // 10-20 digits
            '/\b[A-Z0-9]{10,20}\b/i'          // 10-20 alphanumeric
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return strtoupper(trim($matches[0]));
            }
        }

        return null;
    }

    /**
     * Send error message
     */
    private function sendErrorMessage(string $phone, string $lang): void
    {
        $message = $lang === 'ar'
            ? "⚠️ عذراً، صار خطأ تقني مؤقت.\n\n🔄 يرجى المحاولة مرة ثانية أو أرسل \"قائمة\" لعرض الخيارات.\n\n📞 للمساعدة المباشرة: ٧٢٢٢ ٦٢٨٨"
            : "⚠️ Sorry, a technical error occurred.\n\n🔄 Please try again or send \"menu\" for options.\n\n📞 For immediate help: 7222 6288";

        $this->whatsAppService->sendMessage($phone, $message);
        Log::error('Error message sent', ['phone' => $phone]);
    }

    /**
     * Send tracking not found response
     */
    private function sendTrackingNotFoundResponse(string $phone, string $trackingNumber, string $lang): void
    {
        $message = $lang === 'ar'
            ? "🔍 *ما لقينا الطرد*\n\nرقم التتبع: *{$trackingNumber}*\n\n❓ الأسباب المحتملة:\n• الرقم مو صحيح\n• الطرد ما تسجل بعد\n• الطرد قديم وايد\n\n💡 *الحلول:*\n✓ تأكد من الرقم\n✓ استنا ساعة-ساعتين وحاول مرة ثانية\n✓ تواصل معانا: ٧٢٢٢ ٦٢٨٨\n\n📋 أرسل \"قائمة\" لخيارات ثانية"
            : "🔍 *Shipment Not Found*\n\nTracking number: *{$trackingNumber}*\n\n❓ Possible reasons:\n• Incorrect number\n• Shipment not registered yet\n• Very old shipment\n\n💡 *Solutions:*\n✓ Verify the number\n✓ Wait 1-2 hours and retry\n✓ Contact us: 7222 6288\n\n📋 Send \"menu\" for other options";

        $this->whatsAppService->sendMessage($phone, $message);
        Log::warning('Tracking not found response sent', [
            'phone' => $phone,
            'tracking_number' => $trackingNumber
        ]);
    }

    /**
     * Send delivered message
     */
    private function sendDeliveredMessage(string $phone, string $trackingNumber, string $lang): void
    {
        $message = $lang === 'ar'
            ? "🎉 *تم التوصيل بنجاح!* 🎉\n\nرقم التتبع: *{$trackingNumber}*\n\n✅ طردك وصل بسلامة\n\n⭐ *نتمنى تكون راضي عن خدمتنا*\n\n📝 نسعد بتقييمك وملاحظاتك\n📞 للاستفسارات: ٧٢٢٢ ٦٢٨٨\n\n🙏 شكراً لاستخدامك Parcel Express\n📦 نتطلع نخدمك مرة ثانية"
            : "🎉 *Delivered Successfully!* 🎉\n\nTracking number: *{$trackingNumber}*\n\n✅ Your shipment has been delivered safely\n\n⭐ *We hope you're satisfied with our service*\n\n📝 We appreciate your feedback\n📞 For inquiries: 7222 6288\n\n🙏 Thank you for using Parcel Express\n📦 We look forward to serving you again";

        $this->whatsAppService->sendMessage($phone, $message);
        Log::info('Delivered message sent', [
            'phone' => $phone,
            'tracking_number' => $trackingNumber
        ]);
    }
}