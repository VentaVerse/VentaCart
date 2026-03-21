# Deliveries PH API v3 Documentation

## Endpoint URL

- **Staging:** `https://us-central1-fleet-staging-2333a.cloudfunctions.net`
- **Production:** Provided after SIT

---

## 1. Login (Version 1) - Get Session

| | |
|---|---|
| **Description** | Acquire authorization token |
| **Endpoint** | `/login` |
| **Method** | `POST` |
| **Available to** | Business, Provider |

### Request Headers

```json
{
    "Content-Type": "application/json"
}
```

### Request Body

| Field | Data Type | Required | Description |
|---|---|---|---|
| email | string | yes | email credential |
| password | string | yes | password |

### Sample Request Body

```json
{
    "email": "sandbox_dev@gmail.com",
    "password": "password"
}
```

### Sample Response

```
Status: 200 OK
```
```json
{
    "success": true,
    "results": {
        "apiKey": "fb77c9653bf5e736d191b46bbfed7c2bd",
        "clientId": "SANDBOX_DEV",
        "sessionToken": "b45e2fb77c9653bf5e736d19e1fd18ebbffd0f6d3dd25af118e5eb38851bf4d"
    }
}
```

| Field | Description |
|---|---|
| apiKey | Generated hash on client-side to be used on succeeding API request |
| clientId | Reference Client ID |
| sessionToken | Generated hash on every API login to be used on succeeding API requests. Expires after 20 minutes. |

**Notes:**
- Session token expires after 20 minutes of use.
- Session duration refreshes every login or on API (createPost, getPost, etc.) calls given that the token is still valid.
- QUOTA_EXCEEDED error message occurs when you reach a certain number of login API request per second. For bulk API calls, please use the login API once and reuse the session token. You may also retry the operation after 1-2 seconds.

### Error Messages

**Invalid Email:**
```json
{ "success": false, "results": "EMAIL_NOT_FOUND" }
```

**Invalid Password:**
```json
{ "success": false, "results": "INVALID_PASSWORD" }
```

**Quota Limit Exceeded:**
```json
{ "success": false, "results": "QUOTA_EXCEEDED : Exceeded quota for verifying passwords." }
```

---

## 1.1 Login v2 (JWT Auth)

| | |
|---|---|
| **Description** | Login V2 that uses JWT Auth |
| **Endpoint** | `/loginV2` |
| **Method** | `POST` |
| **Available to** | Business, Provider |

### Request Headers

| Key | Value |
|---|---|
| Content-Type | application/json |

### Request Body

| Field | Data Type | Required | Description |
|---|---|---|---|
| email | string | yes | email credential |
| password | string | yes | password |

### Sample Response

```json
{
    "success": true,
    "results": {
        "accessTokenExpiresAt": 1650941635051,
        "refreshTokenExpiresAt": 1653532435052,
        "accessToken": "eyJhbGciOiJIUzI1NiIs...",
        "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
    }
}
```

**Notes:**
- To use Login API v2 on existing endpoints (e.g. /getpost), `x-access-token` must be defined in the HEADERS instead of `Auth-Token` and value should be `Bearer {{accessToken}}`.
- `apiKey` is no longer required in the Parameters if `x-access-token` is defined in the Headers.
- For LoginV2 bulk API calls, use the Refresh token API (`/refreshToken`).

### Error Messages

**Invalid Email:**
```json
{ "success": false, "results": "INVALID_EMAIL" }
```

**Invalid Password:**
```json
{ "success": false, "results": "INVALID_PASSWORD" }
```

---

## 2. Get Partners

| | |
|---|---|
| **Description** | Get the list of your preferred partners |
| **Endpoint** | `/getPartners` |
| **Method** | `GET` |
| **Available to** | Business, Provider |

### Request Headers (Login V1)

```json
{ "Auth-Token": "<Auth token acquired on api LOGIN>" }
```

### Request Headers (Login V2)

```json
{ "x-access-token": "Bearer <Access token acquired on api LOGIN V2>" }
```

**Note:** To use `x-access-token`, `auth-token` must no longer be defined in the headers.

### Query Parameters (Login V1)

```json
{ "apiKey": "<apiKey will be acquired on login API>" }
```

**Login V2:** No need to define apiKey in the parameters.

### Sample Response

```json
{
    "success": true,
    "results": [
        {
            "id": "SANDBOX_BUSINESS",
            "name": "Sandbox Business",
            "serviceType": "onDemand"
        },
        {
            "id": "SANDBOX_QA",
            "name": "Sandbox_QA",
            "serviceType": "scheduled"
        }
    ]
}
```

---

## 3. Get List of Areas

| | |
|---|---|
| **Description** | Get the list of areas |
| **Endpoint** | `/getListOfAreas` |
| **Method** | `GET` |
| **Available to** | Business |

### Auth Headers

Same as Get Partners (Login V1 or V2).

### Query Parameters (Login V1)

```json
{ "apiKey": "<apiKey will be acquired on login API>" }
```

### Sample Response

```json
{
    "success": true,
    "results": [
        {
            "id": "-KnmC5Y0pFYBquoDL1zo",
            "info": {
                "code": "A1",
                "name": "Area 1",
                "stores": ["-LDnYzs1xbK-FeQ0VPFr"]
            }
        }
    ]
}
```

---

## 4. Get Stores/Hubs

| | |
|---|---|
| **Description** | Get the list of stores/hubs |
| **Endpoint** | `/getListOfStores` |
| **Method** | `GET` |
| **Available to** | Business |

### Auth Headers

Same as Get Partners (Login V1 or V2).

### Query Parameters

| Field | Required | Description |
|---|---|---|
| apiKey | yes (V1 only) | API key from login API |
| areaCode | yes | Area code from getListOfAreas API |

### Sample URL

```
https://us-central1-fleet-staging-2333a.cloudfunctions.net/getListOfStores?apiKey=7c5dd55738b964944f9357bcc7f45ce6d857ff18&areaCode=A1
```

### Sample Response

```json
{
    "success": true,
    "results": [
        {
            "id": "-LDnYzs1xbK-FeQ0VPFr",
            "info": {
                "address": "7391 Building 9 Bakawan St. Corner Mayapis St., San Antonio Village, Makati, 1203 Metro Manila",
                "advanceStart": false,
                "code": "main_store",
                "default": true,
                "latitude": 14.5601823,
                "longitude": 121.00680469999998,
                "name": "Main Store",
                "province": "Metro Manila",
                "provinceKey": "MM"
            }
        }
    ],
    "total": 1
}
```

---

## 5. Get Provider's Create Post v3 Template

| | |
|---|---|
| **Description** | Get provider's create post template and meta fields schema for createPost v3 API. For integration only. |
| **Endpoint** | `/v3/getCreatePostTemplate` |
| **Method** | `GET` |
| **Available to** | Business |

### Auth Headers

Same as Get Partners (Login V1 or V2).

### Query Parameters

| Field | Required | Description |
|---|---|---|
| apiKey | yes (V1 only) | API key from login API |
| partnerId | yes | Client id of desired partner. Can be acquired through getPartners API |
| isAutoReattempt | no | Set to true to get create post template of configured auto reattempt providers. Disregards partnerId if true. |

### Sample Request

```
https://us-central1-fleet-dev-ph.cloudfunctions.net/v3/getCreatePostTemplate?apiKey=...&partnerId=SANDBOX_DX
```

### Sample Response

```json
{
    "success": true,
    "result": {
        "requestBody": {
            "refNo": "",
            "pickupDetails": {
                "customerName": "",
                "contactNumber": "",
                "emailAddress": "",
                "lat": 0,
                "long": 0,
                "tags": [],
                "remarks": "",
                "barangay": "",
                "province": "",
                "postalCode": "",
                "pickupDateTime": "",
                "pickupAddress": "",
                "pickupCity": ""
            },
            "deliveryDetails": {
                "customerName": "",
                "contactNumber": "",
                "emailAddress": "",
                "lat": 0,
                "long": 0,
                "tags": [],
                "remarks": "",
                "barangay": "",
                "province": "",
                "postalCode": "",
                "deliveryDateTime": "",
                "deliveryAddress": "",
                "deliveryCity": "",
                "itemDescription": "",
                "itemPrice": 0,
                "codAmount": 0,
                "productSize": "",
                "length": 0,
                "width": 0,
                "height": 0,
                "weight": 0,
                "metaFields": {
                    "shipment": ""
                }
            }
        },
        "metaFieldSchema": {
            "shipment": {
                "required": true,
                "type": "string",
                "description": "Shipment/Parcel Size",
                "values": ["big-pouch", "box"],
                "details": [
                    { "value": "big-pouch", "description": "Big Pouch (100 pesos within metro manila)" },
                    { "value": "box", "description": "Box" }
                ]
            }
        }
    }
}
```

### Meta Fields Schema

| Field | Data Type | Description |
|---|---|---|
| type | string | data type of a meta field (object or string) |
| required | boolean | indicator if meta field is required |
| description | string | description of a meta field |
| values | array | options/values for the specified meta field |
| details | array | in depth description of options/values |
| properties | object | details for object type meta fields |

---

## 6. Get Quotation

| | |
|---|---|
| **Description** | Get the list of available product's information and rates based on location per provider |
| **Endpoint** | `/getQuotation` |
| **Method** | `POST` |
| **Available to** | Business |

**Notes:**
- Price quotation is NOT available for partners with "on demand" service type.
- Inches is used for length, width and height.
- Kg is used for weight.

### Auth Headers

Same as Get Partners (Login V1 or V2).

### Request Params

```json
{
    "apiKey": "<apiKey>",
    "partnerId": "<provider's id from getPartners API>"
}
```

### Request Body

| Field | Data Type | Required | Description |
|---|---|---|---|
| pickup | object | yes | object for pickup address |
| delivery | object | yes | object for delivery address |

**Fields of pickup and delivery:**

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| province | string | yes | 60 | province |
| city | string | yes | 60 | city |
| barangay | string | no | 60 | barangay |

### Sample Request Body

```json
{
    "pickup": {
        "province": "metro manila",
        "city": "pasig city",
        "barangay": "pinagbuhatan"
    },
    "delivery": {
        "province": "rizal",
        "city": "cainta",
        "barangay": "san andres"
    }
}
```

### Sample Response (Serviceable area)

```json
{
    "success": true,
    "result": {
        "destinations": ["REGION 4-A", "NCR"],
        "estimatedPickupDays": 3,
        "estimatedDeliveryDays": 6,
        "products": [
            {
                "size": "L",
                "price": 165,
                "description": "12 x 18 x 2",
                "name": "Large Pouch",
                "length": 12,
                "width": 18,
                "height": 2,
                "weight": 3
            },
            {
                "size": "XL",
                "price": 310,
                "description": "18 x 12 x 9",
                "name": "Box",
                "length": 18,
                "width": 12,
                "height": 9,
                "weight": 6
            }
        ]
    }
}
```

### Sample Response (Not serviceable area)

```json
{ "success": false, "result": "Pickup/delivery location is not serviceable by the selected provider." }
```

### Error Messages

- Missing Partner Id: `"Missing partnerId. Please check your dashboard documentation."`
- Invalid Partner Id: `"Invalid partner id. Either it's not existing or you don't have rights to post to the partner."`
- Service type "On Demand": `"Price quotation is not yet available for on demand providers."`

---

## 7. Create Post v3

| | |
|---|---|
| **Description** | Create a post based on providers serviceable area |
| **Endpoint** | `/v3/createPost` |
| **Method** | `POST` |
| **Available to** | Business |

### Auth Headers

Same as Get Partners (Login V1 or V2). Must include `Content-Type: application/json`.

### Query Parameters

| Field | Required | Description |
|---|---|---|
| apiKey | yes (V1 only) | API key from login API |
| partnerId | yes | preferred partner/provider from getPartners API |

### Important Notes

- Lat/long fields required for on demand providers.
- Barangay field not required but affects accuracy of serviceable area identification.
- Product sizes vary by provider and pickup/delivery location. Use `/getQuotation` to get available sizes and prices.
- `productSize` not available for on demand providers — leave empty string.
- `metaFields`: Special fields requested by provider. Use `/v3/getCreatePostTemplate` to get schema.

### Request Body Fields

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| refNo | string | yes | 30 | reference number of post |
| isDraft | boolean | no | n/a | set true to save post as draft |
| source | string | no | | origin/source of post (e.g. "Shopify") |
| isAutoReattempt | boolean | no | | Set to true to assign as auto reattempt post |
| businessStoreId | string | no | | create a post by store/hub. Empty/null = default store |
| pickupDetails | object | yes | | object for pickup details |
| deliveryDetails | object | yes | | object for delivery details |
| isPSGC | boolean | no | | Set to true to receive PSGC address format |

### Fields of pickupDetails

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| senderName | string | yes | 80 | name of sender |
| contactNumber | string | yes | 20 | contact number of sender (must be valid) |
| emailAddress | string | no | 60 | email address of sender |
| pickupDateTime | string | yes | n/a | date and time of pickup. Format: `YYYY-MM-DDTHH:mm`. If past dated, overridden by current time + 5 mins |
| pickupAddress | string | yes | 350 | pickup address |
| pickupCity | string | yes | 60 | pickup city |
| province | string | yes | 60 | pickup province |
| barangay | string | no | 60 | pickup barangay (affects accuracy) |
| postalCode | string | no | 10 | pickup postal code |
| lat | number | yes (onDemand) | n/a | latitude of pickup location |
| long | number | yes (onDemand) | | longitude of pickup location |
| tags | array | no | | list of tag id from getTags API |
| remarks | string | no | 350 | pickup remarks |

### Fields of deliveryDetails

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| recipientName | string | yes | 80 | name of recipient |
| contactNumber | string | yes | 20 | contact number of receiver (must be valid) |
| emailAddress | string | no | 60 | email address of receiver |
| deliveryDateTime | string | yes | n/a | date and time of delivery. Format: `YYYY-MM-DDTHH:mm` |
| deliveryAddress | string | yes | 350 | delivery address |
| deliveryCity | string | yes | 60 | delivery city |
| province | string | yes | 60 | delivery province |
| barangay | string | no | 60 | delivery barangay (affects accuracy) |
| postalCode | string | no | 10 | delivery postal code |
| lat | number | yes (onDemand) | n/a | latitude of delivery location |
| long | number | yes (onDemand) | | longitude of delivery location |
| tags | array | no | | list of tag id from getTags API |
| itemDescription | string | no | 350 | details about the item/product |
| remarks | string | no | 350 | delivery remarks |
| productSize | string | yes (scheduled) | n/a | S, M, L, XL (empty string for on demand) |
| vehicleType | string | yes (onDemand) | | MOTORCYCLE, CAR, MPV, PICKUP_TRUCK, LONG_PICKUP_TRUCK, SEDAN, VAN_MULTICAB, CANOPY_MULTICAB, SMALL_PICKUP, CLOSED_VAN_XS, CLOSED_VAN, PICKUP_TRUCK_XS, MINI_DUMP_TRUCK, SIX_WHEELER_FORWARD_TRUCK_XS, SIX_WHEELER_FORWARD_TRUCK, TEN_WHEELER_TRUCK, WING_VAN_40, TRUCK, LD_10W_TRUCK, TEN_WHEELER_WING_VAN_32, VAN_L300, TRUCK330 |
| length | number | no | | length in inches |
| width | number | no | | width in inches |
| height | number | no | | height in inches |
| weight | number | no | | weight in kilograms |
| metaFields | object | no | | metaFields to fulfill provider's required fields |
| quantity | number | no | 5 | quantity |
| items | array | no | | list of item details |
| declaredValue | number | no | 15 | declared item price |
| codAmount | number | no | 15 | cash on delivery amount |

### Fields of items

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| name | string | yes | 350 | item name |
| quantity | number | no | 5 | item quantity |
| unitPrice | number | no | 8 | item unit price |
| declaredValue | number | no | 15 | item declared unit price |
| dimensions | object | no | n/a | item dimensions |

### Fields of dimensions

| Field | Data Type | Required | Description |
|---|---|---|---|
| length | number | no | item length in inches |
| width | number | no | item width in inches |
| height | number | no | item height in inches |
| breadth | number | no | item breadth in inches |
| depth | number | no | item depth in inches |
| weight | number | no | item weight in kilograms |

### Sample Request Body

```json
{
    "refNo": "order-001",
    "businessStoreID": "",
    "isPSGC": false,
    "isAutoReattempt": false,
    "pickupDetails": {
        "senderName": "Christian Pick up",
        "contactNumber": "09123456789",
        "emailAddress": "a@gmail.com",
        "pickupDateTime": "2020-09-29T21:05",
        "pickupAddress": "536 Calbayog St. Highway Hills Mandaluyong City",
        "pickupCity": "Mandaluyong City",
        "province": "Metro Manila",
        "barangay": "Highway Hills",
        "postalCode": "1556",
        "lat": 14.5776292,
        "long": 121.0463673,
        "tags": [],
        "remarks": "my remarks"
    },
    "deliveryDetails": {
        "recipientName": "Christian Delivery",
        "contactNumber": "09123456789",
        "emailAddress": "a@email.com",
        "deliveryDateTime": "2020-09-29T22:06",
        "deliveryAddress": "Vermont Royale, Antipolo",
        "deliveryCity": "Antipolo",
        "province": "Rizal",
        "barangay": "San Roque",
        "postalCode": "1870",
        "declaredValue": 200,
        "productSize": "L",
        "vehicleType": "MOTORCYCLE",
        "width": 0,
        "height": 0,
        "length": 10,
        "weight": 0,
        "codAmount": 250,
        "lat": 14.5776292,
        "long": 121.0463673,
        "tags": [],
        "itemDescription": "Brand New Phone X",
        "remarks": "Please handle with care. Ask the guards for direction.",
        "metaFields": {},
        "quantity": 50,
        "items": [
            {
                "name": "Sample item 1",
                "quantity": 10,
                "unitPrice": 20.00,
                "declaredValue": 5,
                "dimensions": {
                    "length": 2.55,
                    "width": 1.56,
                    "height": 1.28,
                    "breadth": 5.29,
                    "depth": 2.41,
                    "weight": 1.51
                }
            }
        ]
    }
}
```

### Sample Response

```json
{
    "success": true,
    "message": "Job posted on marketplace",
    "result": {
        "postId": "3nHfPLXUGWYhlQ5TcNva",
        "customerName": "Christian Pick up",
        "contactNumber": "09123456789",
        "emailAddress": "a@gmail.com",
        "completionDateTime": 1601384700000,
        "pickupAddress": "536 Calbayog St. Highway Hills Mandaluyong City",
        "pickupCity": "Mandaluyong City",
        "province": "Metro Manila",
        "barangay": "Highway Hills",
        "postalCode": "1556",
        "lat": 14.5776292,
        "long": 121.0463673,
        "approximateShippingFee": 115,
        "billableShippingFee": 0,
        "actualShippingFee": 0,
        "finalShippingFee": 0,
        "businessId": "DRINKKA",
        "businessName": "Drinkka_DEMO",
        "courierName": "Sandbox_DX",
        "createdAt": 1601372523479,
        "partnerId": "SANDBOX_DX",
        "refNo": "order-001",
        "status": "ASSIGNED",
        "items": [...]
    }
}
```

### Error Messages

- **Invalid Request Parameter:** `400` — Returns detailed field-by-field validation errors
- **Missing Partner ID:** `"Missing partnerId. Please check your dashboard documentation."`
- **Invalid Partner ID:** `"Invalid partner id. Either it's not existing or you don't have rights to post to the partner."`
- **Invalid business store id:** `"Invalid store ID. Check your storeId. Remove storeId to use default store."`
- **Invalid pickup/delivery date:** `"Pickup/delivery date and time must be greater than the current time."`
- **Missing coordinates for on demand:** `"Pickup/delivery latitude and longitude is required for on demand providers."`
- **Invalid location:** `"Invalid pickup location."` / `"Invalid delivery location."`
- **Not Serviceable:** `"Pickup location is not serviceable by the provider."` / `"Delivery location is not serviceable by the provider."`
- **Product size not available:** `"Product size not available for the designated service area."`
- **Auto Reattempt:** `"No auto re-attempt settings configured in your Deliveries PH Web account."`

---

## 8. Update Post Status

| | |
|---|---|
| **Description** | Update post status |
| **Endpoint** | `/updatePostStatus` |
| **Method** | `POST` |
| **Available to** | Provider |

### Auth Headers

Same as previous (Login V1 or V2). Must include `Content-Type: application/json`.

### Request Body Fields

| Field | Data Type | Required | Description |
|---|---|---|---|
| postId | string | yes | ID of post to update |
| status | string | yes | status to update |
| remarks | string | no | update remarks from provider |
| metaState | string | no | custom status/state from provider |

### Sample Request Body

```json
{
    "postId": "uuNzkYoOy1VI373BoKY9",
    "status": "START_DELIVERY",
    "remarks": "Rider is on his way",
    "timeStamp": 1575890011178,
    "metaState": ""
}
```

### Sample Response

```json
{ "success": true, "message": "Post successfully updated." }
```

### Status Updates

| Status | Is substatus? | Description |
|---|---|---|
| ASSIGNED | No | Triggered in web if business/customers assign a post to provider |
| ACCEPTED | No | Triggered through manual accept or AUTO ACCEPT |
| REJECTED | No | Triggered through manual reject. Returned in /getpost as "MPREJECTED" |
| ASSIGNING_RIDER | No | Provider is assigning a rider. For on-demand providers only. |
| START_PICKUP | Yes (under "STARTED") | Order is for pickup |
| DONE_PICKUP | Yes (under "STARTED") | Order has been picked up |
| ARRIVED_AT_HUB | Yes (under "STARTED") | Courier delivered to hub. For scheduled providers only. |
| START_DELIVERY | Yes (under "STARTED") | Order is out for delivery |
| DONE | No | Order has been delivered |
| FAILED_PICKUP | No | Failed to pickup order |
| FAILED_DELIVERY | No | Failed to deliver order |
| FAILED_RETURN | No | Failed to return order |
| CANCEL | No | Cancel order |
| IN_TRANSIT_FOR_RETURN | No | Order is out for return |
| RETURNED | No | Order has been returned to sender |
| UPDATE | No | Push event update on job history without updating status. Fill up remarks field. |

---

## 9. Get Post

| | |
|---|---|
| **Description** | Get a specific post or all posts |
| **Endpoint** | `/getPost` |
| **Method** | `GET` |
| **Available to** | Business, Provider |

### Auth Headers

Same as previous (Login V1 or V2).

### Query Parameters

| Parameter | Required | Description |
|---|---|---|
| apiKey | yes (V1 only) | API key from Login API |
| postId | no | Filter by post id. Leave empty to query all |
| partnerId | no | Filter by partner id. Leave empty to query all |
| type | no | Post type: `post` or `opportunity`. Defaults to `opportunity` |
| status | no | Filter by status. Set to `DONE` to get delivered posts |
| businessStoreId | no | Filter by store/hub |
| isPSGC | no | Set to true for PSGC address format |

### Sample Response

```json
{
    "success": true,
    "result": [
        {
            "postId": "uuNzkYoOy1VI373BoKY9",
            "customerName": "Christian Pick up",
            "contactNumber": "09123456789",
            "emailAddress": "a@gmail.com",
            "completionDateTime": 1583836200000,
            "pickupAddress": "Sitio barrio, Angeles Pampanga Philippines",
            "pickupCity": "Mandaluyong City",
            "province": "Metro Manila",
            "lat": 14.5776292,
            "long": 121.0463673,
            "businessId": "SANDBOX_DEV",
            "businessName": "Sandbox_Dev",
            "courierName": "Sandbox_DX",
            "createdAt": 1583381948826,
            "partnerId": "SANDBOX_DX",
            "refNo": "order-001",
            "status": "STARTED",
            "isAutoReattempt": true,
            "subStatus": "DONE_PICKUP",
            "meta": "{...delivery details as JSON string...}",
            "metaFields": {},
            "items": [...]
        }
    ]
}
```

---

## 10. Update Fee

| | |
|---|---|
| **Description** | Pass or update fees of a post. For providers only. |
| **Endpoint** | `/updateFee` |
| **Method** | `POST` |
| **Available to** | Provider |

### Auth Headers

Same as previous (Login V1 or V2).

### Request Body Fields

| Field | Data Type | Required | Description |
|---|---|---|---|
| postId | string | yes | ID of post to be updated |
| remarks | string | no | short description or remarks |
| fees | array | yes | breakdown of fees |

### Fees Object Fields

| Field | Data Type | Required | Description |
|---|---|---|---|
| value | number | yes | amount of fee |
| type | string | yes | type of fee: `shipping`, `return`, `insurance`, `handling`, `others` |

**Notes:**
- List of fees must contain at least one value with fee type of `shipping` or `return`.
- Type of fee must be unique within the list.

### Sample Request Body

```json
{
    "postId": "Wq9FPWxCYVxNgrPrn08D",
    "remarks": "Shipping fee from upon booking.",
    "fees": [
        { "value": 100, "type": "shipping" },
        { "value": 20, "type": "insurance" }
    ]
}
```

### Sample Response

```json
{ "success": true, "result": "Post successfully updated." }
```

---

## 11. Update Meta Fields v2

| | |
|---|---|
| **Description** | Update tracking number and tracking url of pickup or delivery. For providers only. |
| **Endpoint** | `/v2/updateMetaFields` |
| **Method** | `POST` |
| **Available to** | Provider |

### Auth Headers

Same as previous (Login V1 or V2).

### Request Body Fields

| Field | Data Type | Required | Description |
|---|---|---|---|
| postId | string | yes | id of post to update |
| trackingNumber | string | no | tracking number |
| trackingUrl | string | no | tracking URL |

**Notes:**
- Fields under pickup/delivery requires at least one field to update.
- You may update both or pickup/delivery only.
- Undefined tracking number/url fields will not be updated.

### Sample Request Body

```json
{
    "postId": "uuNzkYoOy1VI373BoKY9",
    "pickup": {
        "trackingNumber": "ORDER-001-PIC-ASV",
        "trackingUrl": "https://trackmyorder.com/track?id=ORDER-001-PIC-ASV"
    },
    "delivery": {
        "trackingNumber": "ORDER-001-DEL-ASV",
        "trackingUrl": "https://trackmyorder.com/track?id=ORDER-001-DEL-ASV"
    }
}
```

### Sample Response

```json
{ "success": true, "message": "Post successfully updated." }
```

---

## 12. Get Available Partners

| | |
|---|---|
| **Description** | Get the list of available scheduled providers with product info and rates based on location and dimensions |
| **Endpoint** | `/v3/getAvailablePartners` |
| **Method** | `POST` |
| **Available to** | Business |

**Notes:**
- NOT available for "on demand" service type.
- Inches for length/width/height, Kg for weight.

### Auth Headers

Same as previous (Login V1 or V2).

### Request Body

| Field | Data Type | Required | Description |
|---|---|---|---|
| serviceType | string | yes | `onDemand` or `scheduled` |
| pickup | object | yes | pickup address |
| delivery | object | yes | delivery address |
| dimensions | object | no | dimensions object |
| weight | number | no | weight in kilograms |

**Fields of pickup and delivery:**

| Field | Data Type | Required | Max Length | Description |
|---|---|---|---|---|
| province | string | yes | 60 | province |
| city | string | yes | 60 | city |
| barangay | string | no | 60 | barangay |

**Fields of dimensions:**

| Field | Data Type | Required | Description |
|---|---|---|---|
| length | number | no | length in inches |
| width | number | no | width in inches |
| height | number | no | height in inches |

### Sample Request Body

```json
{
    "serviceType": "Scheduled",
    "pickup": {
        "province": "metro manila",
        "city": "quezon city",
        "barangay": "Katipunan"
    },
    "delivery": {
        "province": "rizal",
        "city": "antipolo city",
        "barangay": "san juan"
    },
    "dimensions": {
        "length": 100,
        "width": 18,
        "height": 6
    },
    "weight": 5
}
```

### Sample Response (Serviceable area)

```json
{
    "success": true,
    "result": [
        {
            "id": "SANDBOX_DX",
            "serviceType": "scheduled",
            "serviceableAreas": [
                { "pickup": { "province": "METRO MANILA", "city": "QUEZON CITY", "barangay": "KATIPUNAN" } },
                { "delivery": { "province": "RIZAL", "city": "ANTIPOLO CITY", "barangay": "SAN JUAN" } }
            ],
            "estimatedDeliveryDays": 5,
            "destinations": ["NCR", "REGION 4-A"],
            "products": [
                { "size": "L", "price": 200, "weight": 20 }
            ]
        }
    ]
}
```

### Sample Response (Non Serviceable)

```json
{ "success": true, "result": [] }
```

### Error Messages

- Invalid delivery/pickup location: `400`
- Service type "onDemand": `"The Service Type: On Demand is currently NOT available for this endpoint."`

---

## 13. Get On Demand Quotation

| | |
|---|---|
| **Description** | Provide rates from different providers depending on selected vehicle, pickup and delivery address |
| **Endpoint** | `/v3/getOnDemandQuotation` |
| **Method** | `POST` |
| **Available to** | Business, Provider |

### Auth Headers

Same as previous (Login V1 or V2).

### Request Body Fields

| Field | Data Type | Required | Description |
|---|---|---|---|
| couriers | array | yes | Array of providers. Empty array = all on demand providers. Options: Lalamove, MrSpeedy, Transportify, Grab |
| pickup_province | string | yes | Pickup Province |
| data.localeKey | string | yes | Default: "en_PH" |
| data.pickup.name | string | yes | Pickup Full Name |
| data.pickup.phone | string | yes | Pickup Contact Number |
| data.pickup.address | string | yes | Pickup Address |
| data.pickup.lat | number | yes | Pickup Latitude |
| data.pickup.long | number | yes | Pickup Longitude |
| data.delivery.name | string | yes | Delivery Full Name |
| data.delivery.phone | string | yes | Delivery Contact Number |
| data.delivery.address | string | yes | Delivery Address |
| data.delivery.lat | number | yes | Delivery Latitude |
| data.delivery.long | number | yes | Delivery Longitude |
| data.remarks | string | no | Remarks |
| data.scheduleAt | number | yes | Date and Time in milliseconds |
| data.vehicleType | string | yes | MOTORCYCLE, CAR, VAN, MPV, L300, ECONOMY, PICKUP_TRUCK, LONG_PICKUP_TRUCK, WING_VAN |
| data.dimensions.height | number | yes | Height in inches |
| data.dimensions.width | number | yes | Width in inches |
| data.dimensions.depth | number | yes | Depth in inches |
| data.dimensions.weight | number | yes | Weight in kilograms |

### Sample Request Body

```json
{
    "couriers": ["grab", "lalamove"],
    "pickup_province": "Metro Manila",
    "data": {
        "localeKey": "en_PH",
        "pickup": {
            "name": "Mariel Quibal",
            "phone": "09561441650",
            "address": "San agustine ave blk 2 lot 41 Asilo Ville Pinagbuhatan Pasig City Metro Manila",
            "lat": 14.559774,
            "long": 121.086519
        },
        "delivery": {
            "name": "Mara Quibal",
            "phone": "09561441650",
            "address": "Unit 802, EcoTower, 32nd Street corner 9th Avenue, BGC, Taguig, 1634 Metro Manila",
            "lat": 14.5528425,
            "long": 121.0518742
        },
        "remarks": "sample remarks",
        "scheduleAt": 1613566801000,
        "vehicleType": "MOTORCYCLE",
        "dimensions": {
            "height": 5,
            "width": 5,
            "depth": 5,
            "weight": 5
        }
    }
}
```

### Sample Response

```json
{
    "success": true,
    "result": [
        { "courier": "GRAB", "error": "Bad Input" },
        { "courier": "LALAMOVE", "rate": 113, "vehicleType": "MOTORCYCLE" }
    ]
}
```

---

## 14. Refresh Token

| | |
|---|---|
| **Description** | Renew Access Token and Refresh Token of LoginV2 before expiration |
| **Endpoint** | `/refreshToken` |
| **Method** | `POST` |
| **Available to** | Business, Provider |

### Request Headers

| Key | Value |
|---|---|
| Content-Type | application/json |

### Request Body

| Field | Data Type | Required | Description |
|---|---|---|---|
| refreshToken | string | yes | Value should be "Bearer" plus the last generated Refresh token |

### Sample Request Body

```json
{
    "refreshToken": "Bearer eyJhbGciOiJIUzI1NiIs..."
}
```

### Sample Response

```json
{
    "success": true,
    "results": {
        "accessTokenExpiresAt": 1650957491706,
        "refreshTokenExpiresAt": 1653548291707,
        "accessToken": "eyJhbGciOiJIUzI1NiIs...",
        "refreshToken": "eyJhbGciOiJIUzI1NiIs..."
    }
}
```

### Error Messages

- Invalid/Expired refresh token: `"Invalid Refresh Token or Refresh token expired."`
- Missing refresh token: `"Missing Refresh Token."`

---

## 15. Cancel Post API (On Demand)

| | |
|---|---|
| **Description** | Cancel post via API |
| **Endpoint** | `/v3/cancelPost` |
| **Method** | `POST` |
| **Available to** | Business |

### Request Headers

```
Content-Type: application/json
auth-token: <Auth token acquired on api LOGIN>
```

### Query Parameters

```json
{ "apiKey": "<apiKey will be acquired on login API>" }
```

### Request Body

| Key | Required | Description |
|---|---|---|
| trackingNumber | yes | Tracking Number of provider |
| postId | yes | Post Id of order |
| courierId | yes | Provider of the order |

### Sample Request Body

```json
{
    "trackingNumber": "720664",
    "postId": "ubyhvwENm8VRbsMPDGFg",
    "courierId": "SANDBOX_QA"
}
```

### Sample Response

```json
{ "message": "Success" }
```

---

## HTTP Response Codes

| Status | Message | Description |
|---|---|---|
| 200 | OK | Success |
| 400 | Bad Request | Invalid syntax or invalid parameters |
| 401 | Unauthorized | Missing/invalid apiKey or expired auth-token |
| 500 | Internal Server Error | Unhandled exception. Contact production team. |

---

## HTTP Trigger Webhook Tokens

For tracking number and URL, availability depends on when the provider pushes the tracking number/URL.

### Available Tokens

| Label | Token | Description |
|---|---|---|
| Business ID | `{businessId}` | ID of the Business Account |
| Courier ID | `{courierId}` | ID of the Courier Account |
| Reference No. | `{refNo}` | Reference Number of the booking |
| Status | `{status}` | Status of the booking |
| Business Name | `{businessName}` | Name of the Business |
| Courier Name | `{courierName}` | Name of the Courier |
| Pickup Address | `{pickup.address}` | Pickup address |
| Pickup City | `{pickup.city}` | Pickup city |
| Pickup Completion DateTime | `{pickup.completionDateTime}` | Expected pickup completion |
| Sender Name | `{pickup.customerName}` | Name of Sender |
| Pickup Contact Number | `{pickup.contactNumber}` | Sender contact |
| Pickup Email | `{pickup.email}` | Sender email |
| Pickup Lat | `{pickup.lat}` | Pickup latitude |
| Pickup Long | `{pickup.long}` | Pickup longitude |
| Pickup Province | `{pickup.province}` | Pickup province |
| Pickup Item Description | `{pickup.details}` | Item description |
| Pickup Remarks | `{pickup.remarks}` | Pickup remarks |
| Pickup Postal Code | `{pickup.postalCode}` | Pickup postal code |
| Pickup Barangay | `{pickup.barangay}` | Pickup barangay |
| Delivery COD Amount | `{delivery.codAmount}` | COD amount |
| Delivery Completion DateTime | `{delivery.completionDateTime}` | Expected delivery completion |
| Delivery Contact Number | `{delivery.contactNumber}` | Recipient contact |
| Recipient Name | `{delivery.customerName}` | Recipient name |
| Delivery Address | `{delivery.address}` | Delivery address |
| Delivery City | `{delivery.city}` | Delivery city |
| Delivery Item Description | `{delivery.details}` | Item description |
| Product Height | `{product.height}` | Product height |
| Product Length | `{product.lenght}` | Product length |
| Product Width | `{product.width}` | Product width |
| Delivery Email | `{delivery.email}` | Recipient email |
| Declared Value | `{delivery.itemPrice}` | Declared item price |
| Delivery Lat | `{delivery.lat}` | Delivery latitude |
| Delivery Long | `{delivery.long}` | Delivery longitude |
| Delivery Meta Tags | `{delivery.metaTags}` | Meta tags |
| Delivery Province | `{delivery.province}` | Delivery province |
| Delivery Remarks | `{delivery.remarks}` | Delivery remarks |
| Delivery Postal Code | `{delivery.postalCode}` | Delivery postal code |
| Delivery Barangay | `{delivery.barangay}` | Delivery barangay |
| Delivery Weight | `{delivery.weight}` | Item weight |
| Tracking Number | `{trackingNumber}` | Package tracking number |
| Tracking URL | `{trackingUrl}` | Tracking URL (usually for on-demand providers) |
| Post ID | `{postId}` | Booking ID |
| Opportunity ID | `{opportunityId}` | Booking ID (courier's view) |
| Product Size | `{product.size}` | Product size |
| Approximate Shipping Fee | `{fee.approximateShipping}` | Quoted shipping cost |
| Actual Shipping Fee | `{fee.actualShipping}` | Shipping cost upon booking |
| Final Shipping Fee | `{fee.finalShipping}` | Final shipping including all additional fees |
| Last Update Remarks | `{lastUpdateRemarks}` | Last remarks updated |
| Substatus | `{subStatus}` | Sub status of the booking |
| Custom Field | `{customField}` | Customized field |
| List of Items | `{listOfItems}` | List of items in booking |
| Number of Attempts | `{numberOfAttempts}` | Delivery attempts |
| Shareable Link | `{shareableLink}` | DPH tracking link for customers |
| Delivery Quantity | `{delivery.quantity}` | Quantity |
| Dimension Height | `{dimension.height}` | Item height |
| Dimension Width | `{dimension.width}` | Item width |
| Dimension Length | `{dimension.length}` | Item length |

### Webhook Statuses

All tokens are available across all statuses: Draft, Assign, Unpost, Accept, Cancel, Assigning Rider, Start Pickup, Done Pickup, Arrived at hub, Start Delivery, Reject, Done, In transit for return, Returned, Failed Delivery, Failed Pickup, Failed Return.
