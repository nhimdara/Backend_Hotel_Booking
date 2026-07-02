# Frontend API Guide

Use this document as the fetch reference for the frontend.

## Base URL

```txt
http://127.0.0.1:8000/api
```

If your Laravel backend runs on another host or port, replace the base URL.

## Headers

Public endpoints do not need a token. Authenticated and admin endpoints need the Sanctum token returned by login/register.

```js
const API_URL = "http://127.0.0.1:8000/api";

const jsonHeaders = (token) => ({
  "Content-Type": "application/json",
  Accept: "application/json",
  ...(token ? { Authorization: `Bearer ${token}` } : {}),
});
```

For image upload endpoints, use `FormData` and do not manually set `Content-Type`.

## Auth

### Register

`POST /register`

```json
{
  "name": "Michael Watson",
  "email": "michael.watson@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Returns:

```json
{
  "message": "Registration successful.",
  "user": {},
  "token": "plain-text-token"
}
```

### Login

`POST /login`

```json
{
  "email": "michael.watson@example.com",
  "password": "password"
}
```

Returns `user` and `token`.

### Logout

`POST /logout`

Auth required.

### Profile

`GET /profile`

Auth required.

### Update Profile

`PUT /profile`

Auth required.

```json
{
  "name": "New Name",
  "email": "new@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

All fields are optional.

## Hotels

### List Hotels

`GET /hotels`

Public endpoint.

Optional query params:

```txt
location=Paris
min_price=100
max_price=500
star_rating=4
min_rating=4.5
badge=Top Rated
sort_by=price_per_night|review_score|star_rating
sort_dir=asc|desc
per_page=15
```

Example:

```js
const res = await fetch(`${API_URL}/hotels?location=Paris&per_page=12`, {
  headers: { Accept: "application/json" },
});
const data = await res.json();
```

Returns:

```json
{
  "stats": {
    "matches": 1,
    "avg_price": 310,
    "avg_rating": 4.7
  },
  "hotels": {
    "data": []
  }
}
```

### Show Hotel

`GET /hotels/{hotelId}`

Public endpoint. Returns hotel details, badges, active room types, and bookings count.

## Bookings

All booking endpoints require auth.

### List Bookings

`GET /bookings`

Regular users see their own bookings. Admin users see all bookings.

### Create Booking

`POST /bookings`

```json
{
  "hotel_id": 1,
  "check_in": "2026-07-12",
  "check_out": "2026-07-15",
  "guests": 2,
  "room_type": "suite",
  "special_requests": "Ocean view preferred."
}
```

Valid `room_type` values:

```txt
standard, deluxe, suite, presidential
```

Returns:

```json
{
  "message": "Booking created. Complete payment to confirm.",
  "booking": {
    "id": 1,
    "booking_reference": "SE-ABCD-1234",
    "total_price": "1740.00",
    "status": "pending"
  }
}
```

### Show Booking

`GET /bookings/{bookingId}`

### Update Booking

`PUT /bookings/{bookingId}`

For normal users:

```json
{
  "special_requests": "Late check-in.",
  "status": "cancelled"
}
```

Admin users can also update `status`, `room_type`, `check_in`, `check_out`, and `guests`.

### Delete Booking

`DELETE /bookings/{bookingId}`

## Payments

All payment endpoints require auth.

### Initiate Payment

`POST /bookings/{bookingId}/payment`

```json
{
  "method": "qr_scan"
}
```

Valid `method` values:

```txt
card, qr_scan, bank_transfer
```

Returns:

```json
{
  "message": "Payment hold created. Scan the QR code to authorize.",
  "payment": {
    "id": 1,
    "booking_id": 1,
    "reference": "PAY-ABC123",
    "amount": "1740.00",
    "method": "qr_scan",
    "status": "pending",
    "qr_code_payload": "http://127.0.0.1:8000/images/ada-pay-qr.jpg",
    "hold_expires_at": "2026-07-02T04:00:00.000000Z",
    "points_earned": 1740
  },
  "seconds_remaining": 900
}
```

Display the QR code:

```jsx
<img src={payment.qr_code_payload} alt="Payment QR code" />
```

### Payment Status

`GET /payments/{paymentId}/status`

Returns:

```json
{
  "status": "pending",
  "seconds_remaining": 845
}
```

### Authorize Payment

`POST /payments/{paymentId}/authorize`

This simulates the bank/webhook confirming payment. It changes the payment to `completed`, changes the booking from `pending` to `confirmed`, and returns the updated payment, booking, and loyalty point balance.

## Admin: Hotels

Admin token required.

### Create Hotel

`POST /hotels`

JSON body for URL images:

```json
{
  "name": "Grand Horizon Resort",
  "slug": "grand-horizon-resort",
  "description": "Stunning views with world-class amenities.",
  "location": "Amalfi Coast",
  "country": "Italy",
  "latitude": 40.6333,
  "longitude": 14.6029,
  "price_per_night": 850,
  "star_rating": 5,
  "amenities": ["Pool Access", "Fine Dining", "Spa"],
  "image_urls": ["https://example.com/hotel.jpg"],
  "badge_ids": [1, 2]
}
```

For file upload, send `multipart/form-data` with `image` or `images[]`.

### Update Hotel

`PUT /hotels/{hotelId}`

Same fields as create, but all fields are optional.

### Delete Hotel

`DELETE /hotels/{hotelId}`

## Admin: Users

Admin token required.

### List Users

`GET /admin/users`

### Change User Role

`PUT /admin/users/{userId}/role`

```json
{
  "role": "admin"
}
```

Valid roles:

```txt
user, admin
```

### Delete User

`DELETE /admin/users/{userId}`

## Admin: Dashboard

Admin token required.

### Overview

`GET /admin/dashboard/overview?hotel_id=1`

Returns occupancy, check-ins, and revenue summary.

### Revenue Performance

`GET /admin/dashboard/revenue-performance?hotel_id=1&range=current`

`range` can be:

```txt
current, yearly
```

Returns candlestick-style revenue series.

### Recent Bookings

`GET /admin/dashboard/recent-bookings?hotel_id=1&limit=5`

### Bookings Summary

`GET /admin/dashboard/bookings-summary?hotel_id=1`

## Fetch Helper Example

```js
export async function apiFetch(path, options = {}) {
  const token = localStorage.getItem("token");

  const res = await fetch(`${API_URL}${path}`, {
    ...options,
    headers: {
      ...jsonHeaders(token),
      ...(options.headers || {}),
    },
  });

  const data = await res.json().catch(() => null);

  if (!res.ok) {
    throw new Error(data?.message || "API request failed");
  }

  return data;
}
```

Example login:

```js
const data = await apiFetch("/login", {
  method: "POST",
  body: JSON.stringify({
    email: "michael.watson@example.com",
    password: "password",
  }),
});

localStorage.setItem("token", data.token);
```

Example create booking then payment:

```js
const bookingData = await apiFetch("/bookings", {
  method: "POST",
  body: JSON.stringify({
    hotel_id: 1,
    check_in: "2026-07-12",
    check_out: "2026-07-15",
    guests: 2,
    room_type: "suite",
  }),
});

const paymentData = await apiFetch(`/bookings/${bookingData.booking.id}/payment`, {
  method: "POST",
  body: JSON.stringify({ method: "qr_scan" }),
});

const qrImageUrl = paymentData.payment.qr_code_payload;
```

## Common Errors

```json
{
  "message": "Unauthenticated."
}
```

Missing or invalid token.

```json
{
  "message": "Forbidden."
}
```

User does not own the record or is not admin.

```json
{
  "message": "The given data was invalid.",
  "errors": {}
}
```

Validation failed. Show the field errors in the frontend form.
