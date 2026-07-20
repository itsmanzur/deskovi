# Deskovi Guest Verification Contract v1

Guest support without a WordPress account.

## Flow

1. `POST /itsdesk/v1/guest/verify/start` `{ order_id, email }`  
   - Order must exist (`wc_get_order`).  
   - Billing email must match (case-insensitive).  
   - Rate limit: 5 starts / 15 minutes / IP+email.  
   - OTP emailed via `wp_mail` (10 minute TTL).

2. `POST /itsdesk/v1/guest/verify/confirm` `{ order_id, email, code }`  
   - Sets HttpOnly cookie `itsdesk_guest` (signed, 24h).

3. `GET /itsdesk/v1/guest/session` — current session or null.  
4. `DELETE /itsdesk/v1/guest/session` — clear cookie.

## Ticket ownership

Guest tickets: `customer_user_id: 0`, `customer_email`, `guest_order_id` / `order_id`.  
Customer REST (`/customer/tickets*`, `/customer/orders*`, `/customer/unread`) accepts logged-in **or** valid guest cookie.

Guests may only list/view the verified order (not full order history).
