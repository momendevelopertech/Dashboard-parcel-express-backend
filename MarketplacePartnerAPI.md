
# 📦 Marketplace Partner API Documentation

Version: API v1  

---

## 🌐 Partner Environments

### **Sandbox Environment**
- **URL:** `https://sandbox-api.parcelexpress.om/v1`
- **Purpose:** Testing and development
- **Data:** No production data, test orders only
- **Credentials:** Separate from production
- **Rate Limits:** 200 requests/minute

### **Production Environment**
- **URL:** `https://api.parcelexpress.om/v1`
- **Purpose:** Live integrations
- **Credentials:** Production keys only
- **Rate Limits:** 120 requests/minute (adjustable per SLA)

---

## 🔐 Authentication

**Method:** API Key + HMAC Signature

Each partner receives:
- `api_key` (public identifier)
- `api_secret` (private, never transmitted)

### **Request Headers**
```
X-API-Key: PARTNER_123
X-Timestamp: 2025-11-02T13:00:00Z
X-Signature: <hmac_sha256_signature>
```

### **Signature Generation**
```
StringToSign = <HTTP_METHOD>\n<REQUEST_PATH>\n<TIMESTAMP>\n<SHA256_BODY_HASH>
Signature = HMAC-SHA256(StringToSign, api_secret)
```

### **Security Requirements**
- HTTPS only (TLS 1.2+)
- Timestamp must be within ±5 minutes of server time

---

## 🧪 Test Authentication

### **GET /v1/partners/test**

**Headers:**
```
Accept: application/json
X-API-Key: {{api_key}}
api_secret: {{api_secret}}
X-Timestamp: <timestamp>
X-Signature: <signature>
```

**Response:**
```json
{
  "message": "Partner authentication successful",
  "partner_id": "PARTNER_123"
}
```

---

## 🚚 Shipments

### **GET /v1/shipments**  
Retrieve a paginated list of partner shipments.

**Query Parameters:**
| Parameter | Type | Description |
|------------|------|-------------|
| `status` | string | Filter by shipment status (e.g. `in_transit`, `delivered`) |
| `from` | ISO 8601 date | Start date for filtering |
| `to` | ISO 8601 date | End date for filtering |
| `limit` | integer | Results per page (default: 50, max: 200) |
| `page` | integer | Page number |

**Example Request:**
```
GET /v1/shipments?status=in_transit&from=2025-10-01&to=2025-10-31&limit=1&page=1
```

**Example Response:**
```json
{
  "data": [
    {
      "internal_shipment_id": "5",
      "status": "CREATED",
      "tracking_numbers": ["PE101125183970"],
      "created_at": "2025-11-10T15:38:21+04:00",
      "recipient": {
        "name": "John Doe",
        "phone": "98225395"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 1,
    "total_records": 2,
    "per_page": 50
  }
}
```

---

### **GET /v1/shipments/{id}**  
Retrieve details for a specific shipment.

**Path Parameter:**
| Parameter | Description |
|------------|-------------|
| `id` | Internal shipment ID |

**Example Request:**
```
GET /v1/shipments/5
```

**Example Response:**
```json
{
  "internal_shipment_id": "5",
  "status": "CREATED",
  "tracking_numbers": ["PE101125183970"],
  "created_at": "2025-11-10T15:38:21+04:00",
  "recipient": {
    "name": "John Doe",
    "phone": "98225395"
  }
}
```

---

## 📦 Tracking Endpoints

### **GET /v1/trackings**  
Retrieve all tracking events for partner shipments.

**Query Parameters:**
| Parameter | Type | Description |
|------------|------|-------------|
| `tracking_number` | string | Filter by tracking number |
| `from` | ISO 8601 date | Start date for filtering |
| `limit` | integer | Results per page (default: 50) |
| `page` | integer | Page number |

**Example Request:**
```
GET /v1/trackings?tracking_number=PE101125183970&from=2025-11-01&limit=1&page=1
```

**Example Response:**
```json
{
  "data": [
    {
      "tracking_number": "PE101125183970",
      "current_status": "CREATED",
      "events": [
        {
          "status": "CREATED",
          "description": "Shipment Created",
          "location": { "city": "Muscat Hub" },
          "timestamp": "2025-11-10T15:38:21+04:00"
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 1,
    "total_records": 2,
    "per_page": 50
  }
}
```

---

### **GET /v1/trackings/{tracking_number}**  
Retrieve detailed tracking information for a single shipment.

**Path Parameter:**
| Parameter | Description |
|------------|-------------|
| `tracking_number` | The tracking number of the shipment |

**Example Request:**
```
GET /v1/trackings/PE091125673044
```

**Example Response:**
```json
{
  "tracking_number": "PE091125673044",
  "current_status": "CREATED",
  "events": [
    {
      "status": "PRINT",
      "description": "Airway bill has been printed",
      "location": { "city": "Muscat Hub" },
      "timestamp": "2025-11-09T19:28:08+04:00"
    }
  ]
}
```

---

© 2025 Parcel Express Oman | Partner API v1
