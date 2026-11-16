# Groq → Cloudflare: cấu hình chatbot

Bản tích hợp dùng biến môi trường phía server. Không ghi key vào Git, JavaScript hoặc gửi trong hội thoại. Thu hồi hai key đã chia sẻ và tạo lại trước khi sử dụng.

Đặt trong cấu hình môi trường PHP-FPM/Apache/container (hoặc biến môi trường shell trước khi chạy PHP server):

```dotenv
GROQ_API_KEY=your_new_groq_key
CLOUDFLARE_API_TOKEN=your_new_cloudflare_token
CLOUDFLARE_ACCOUNT_ID=your_cloudflare_account_id
```

Ứng dụng đọc `getenv`, không tự tải file .env. PHP cần extension curl và mbstring. Khởi động lại dịch vụ PHP sau khi đổi môi trường. Không nhập key Groq vào ô OpenAI trong admin.

Groq dùng openai/gpt-oss-120b, Cloudflare dùng @cf/meta/llama-3.1-8b-instruct-fp8-fast. Account ID Cloudflare phải là 32 ký tự hex; nếu sai, kết nối Cloudflare không được bật.

Khi có ít nhất một cấu hình hợp lệ, chat dùng hai nhà cung cấp theo thứ tự, không chuyển sang OpenAI. Nếu chưa có cấu hình nào, giữ hành vi OpenAI/dự phòng cũ. Không sửa AI chấm bài đánh giá: tích hợp này chỉ dành cho chat.

Một lần thử mỗi nhà cung cấp; timeout tối đa 18 giây mỗi lần, khoảng 36 giây tổng network. Chuyển khi 429, timeout, 401/402/408 hoặc lỗi máy chủ. Không chuyển khi request sai hoặc có refusal/content_filter. Khi cả hai lỗi, hiện thông báo rõ, không giả câu trả lời AI. Một lượt chat vẫn tính một lượt trong quota học sinh.

Đây là bản failover cơ bản: chưa có circuit breaker/cooldown dùng chung toàn server, dashboard lỗi nhà cung cấp hoặc health check riêng trong admin. HTTP 403 dừng chuỗi để tránh chuyển nhà cung cấp khi có chặn chính sách; admin cần kiểm tra quyền token. Không cam kết model miễn phí vô hạn; quota áp dụng theo tài khoản dịch vụ.

Chạy `php tests/chat-providers-test.php` để kiểm thử bằng transport giả. Chạy luồng HTTP hiện có để hồi quy. Chưa xác minh API live; cần thử với key mới trên máy chủ. Nguồn endpoint: https://console.groq.com/docs/openai và https://developers.cloudflare.com/workers-ai/configuration/open-ai-compatibility/ .
