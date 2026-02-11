# Container Module Documentation

## Overview

The Container module provides functionality to manage containers that can hold shipments. This allows you to group multiple shipments together in a physical or logical container, track their weight/volume utilization, and manage shipment assignments.

## Features

- **Create Containers**: Create containers with customizable types, weight, and volume limits
- **Tracking**: Unique tracking numbers for easy container identification and tracing
- **Shipment Management**: Add, remove, or move shipments between containers
- **Capacity Tracking**: Automatically track current weight and volume usage
- **Status Management**: Monitor container status (ACTIVE, FULL, IN_TRANSIT, DELIVERED, ARCHIVED)
- **Bulk Operations**: Add or remove multiple shipments at once
- **Facility Association**: Containers can be associated with hubs, branches, or other facilities
- **User Tracking**: Track who created each container

## Database Schema

### Containers Table

```sql
CREATE TABLE containers (
    id BIGINT PRIMARY KEY,
    container_number VARCHAR(255) UNIQUE INDEX,
    tracking_no VARCHAR(255) UNIQUE INDEX,
    container_type VARCHAR(255),
    max_weight DECIMAL(10,2),
    current_weight DECIMAL(10,2) DEFAULT 0,
    max_volume DECIMAL(10,2),
    current_volume DECIMAL(10,2) DEFAULT 0,
    shipment_count INT DEFAULT 0,
    status VARCHAR(255) DEFAULT 'ACTIVE',
    facility_type VARCHAR(255),
    facility_id BIGINT,
    created_by BIGINT FOREIGN KEY (users),
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP (soft delete)
);
```

### Shipments Table (Updated)

Added `container_id` foreign key to associate shipments with containers:
```sql
ALTER TABLE shipments ADD container_id BIGINT FOREIGN KEY (containers);
```

## API Endpoints

### Get All Containers
```http
GET /api/v1/containers
```

**Query Parameters:**
- `status` - Filter by container status (ACTIVE, FULL, IN_TRANSIT, DELIVERED, ARCHIVED)
- `container_type` - Filter by container type
- `facility_type` - Filter by facility type
- `facility_id` - Filter by facility ID
- `search` - Search by container number
- `per_page` - Items per page (default: 15)

**Response:**
```json
{
    "message": "Containers retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "container_number": "CNT-ABC12345",
                "tracking_no": "CTR-XYZ123456AB",
                "container_type": "BOX",
                "max_weight": 100.00,
                "current_weight": 45.50,
                "max_volume": 50.00,
                "current_volume": 25.00,
                "shipment_count": 5,
                "status": "ACTIVE",
                "created_at": "2026-02-06T10:00:00Z",
                "updated_at": "2026-02-06T10:00:00Z"
            }
        ],
        "total": 50,
        "per_page": 15
    }
}
```

### Create Container
```http
POST /api/v1/containers
Content-Type: application/json

{
    "container_type": "BOX",
    "max_weight": 100,
    "max_volume": 50,
    "facility_type": "Hub",
    "facility_id": 1,
    "notes": "Standard shipping box"
}
```

**Response:**
```json
{
    "message": "Container created successfully",
    "data": {
        "id": 1,
        "container_number": "CNT-XYZ98765",
        "tracking_no": "CTR-ABC987654XY",
        "container_type": "BOX",
        "max_weight": 100.00,
        "current_weight": 0.00,
        "max_volume": 50.00,
        "current_volume": 0.00,
        "shipment_count": 0,
        "status": "ACTIVE",
        "created_by": 5,
        "created_at": "2026-02-06T10:00:00Z",
        "updated_at": "2026-02-06T10:00:00Z"
    }
}
```

### Get Container Details
```http
GET /api/v1/containers/{container_id}
```

**Response:**
```json
{
    "message": "Container details retrieved successfully",
    "data": {
        "id": 1,
        "container_number": "CNT-XYZ98765",
        "tracking_no": "CTR-ABC987654XY",
        "container_type": "BOX",
        "status": "ACTIVE",
        "shipment_count": 5,
        "max_weight": 100.00,
        "current_weight": 45.50,
        "remaining_weight": 54.50,
        "max_volume": 50.00,
        "current_volume": 25.00,
        "remaining_volume": 25.00,
        "utilization_percentage": 45.5,
        "is_full": false,
        "shipments": {
            "TRK-001": 1,
            "TRK-002": 2
        },
        "created_by": "John Doe",
        "created_at": "2026-02-06T10:00:00Z",
        "updated_at": "2026-02-06T10:00:00Z"
    }
}
```

### Update Container
```http
PUT /api/v1/containers/{container_id}
Content-Type: application/json

{
    "container_type": "PALLET",
    "max_weight": 150,
    "max_volume": 75,
    "notes": "Updated container specs"
}
```

### Delete Container
```http
DELETE /api/v1/containers/{container_id}
```

### Add Shipment to Container
```http
POST /api/v1/containers/{container_id}/add-shipment
Content-Type: application/json

{
    "shipment_id": 42
}
```

**Response:**
```json
{
    "message": "Shipment added to container successfully",
    "data": {
        "container": {
            "id": 1,
            "container_number": "CNT-XYZ98765",
            "shipment_count": 6,
            "current_weight": 50.25
        },
        "shipment": {
            "id": 42,
            "tracking_no": "TRK-003",
            "container_id": 1
        }
    }
}
```

### Remove Shipment from Container
```http
POST /api/v1/containers/{container_id}/remove-shipment
Content-Type: application/json

{
    "shipment_id": 42
}
```

### Add Multiple Shipments
```http
POST /api/v1/containers/{container_id}/add-multiple-shipments
Content-Type: application/json

{
    "shipment_ids": [42, 43, 44, 45]
}
```

**Response:**
```json
{
    "message": "All shipments added to container successfully",
    "data": {
        "success": [42, 43, 44, 45],
        "failed": []
    },
    "container": {
        "id": 1,
        "shipment_count": 9,
        "current_weight": 75.50
    }
}
```

### Remove Multiple Shipments
```http
POST /api/v1/containers/{container_id}/remove-multiple-shipments
Content-Type: application/json

{
    "shipment_ids": [42, 43]
}
```

### Move Shipments Between Containers
```http
POST /api/v1/containers/{from_container_id}/move-shipments
Content-Type: application/json

{
    "to_container_id": 2,
    "shipment_ids": [42, 43]
}
```

### Get Container Shipments
```http
GET /api/v1/containers/{container_id}/shipments
```

**Response:**
```json
{
    "message": "Container shipments retrieved successfully",
    "data": [
        {
            "id": 42,
            "tracking_no": "TRK-001",
            "value": 15.75,
            "status": "IN_TRANSIT"
        },
        {
            "id": 43,
            "tracking_no": "TRK-002",
            "value": 20.50,
            "status": "IN_TRANSIT"
        }
    ]
}
```

### Empty Container
```http
POST /api/v1/containers/{container_id}/empty
```

**Response:**
```json
{
    "message": "Container emptied successfully",
    "data": {
        "container": {
            "id": 1,
            "shipment_count": 0,
            "current_weight": 0.00,
            "status": "ACTIVE"
        },
        "removed_shipments_count": 5
    }
}
```

### Update Container Status
```http
POST /api/v1/containers/{container_id}/update-status
Content-Type: application/json

{
    "status": "IN_TRANSIT"
}
```

**Allowed Statuses:** `ACTIVE`, `FULL`, `IN_TRANSIT`, `DELIVERED`, `ARCHIVED`

## Service Class - ContainerService

The `ContainerService` class handles all container-related business logic.

### Usage Examples

```php
<?php

use App\Services\ContainerService;
use App\Models\Container;
use App\Models\Shipment;

$containerService = app(ContainerService::class);

// Create a new container
$container = $containerService->createContainer([
    'container_type' => 'BOX',
    'max_weight' => 100,
    'max_volume' => 50,
    'facility_type' => 'Hub',
    'facility_id' => 1,
    'created_by' => auth()->id(),
]);

// Add a shipment
$shipment = Shipment::find(42);
$containerService->addShipment($container, $shipment);

// Add multiple shipments
$results = $containerService->addMultipleShipments($container, [42, 43, 44]);
// Returns: ['success' => [42, 43, 44], 'failed' => []]

// Remove a shipment
$containerService->removeShipment($container, $shipment);

// Check if container can accept weight
if ($container->canAcceptWeight(25)) {
    // Container has capacity
}

// Check if container is full
if ($container->isFull()) {
    // Container is at capacity
}

// Get container utilization
$percentage = $container->getUtilizationPercentage(); // Returns 45.5

// Empty container (remove all shipments)
$removedCount = $containerService->emptyContainer($container);

// Move shipments between containers
$results = $containerService->moveShipments($container1, $container2, [42, 43]);

// Get containers with filters
$containers = $containerService->getContainers([
    'status' => 'ACTIVE',
    'container_type' => 'BOX',
    'facility_type' => 'Hub',
    'facility_id' => 1,
], 20); // 20 per page

// Get container details with statistics
$details = $containerService->getContainerDetails($container);
```

## Model Relationships

### Container Model
```php
// Get all shipments in container
$container->shipments(); // HasMany relationship

// Get the facility (Hub, Branch, etc.)
$container->facility(); // MorphTo relationship

// Get the user who created it
$container->creator(); // BelongsTo User relationship
```

### Shipment Model
```php
// Get the container this shipment is in
$shipment->container(); // BelongsTo Container relationship
```

## Authorization

The following authorization gates are used:

- `Container access` - View containers
- `Container create` - Create new containers
- `Container update` - Update containers and shipment assignments
- `Container delete` - Delete containers

You can customize these in your authorization provider.

## Validation Rules

### Create/Update Container
- `container_number`: nullable, string, unique
- `tracking_no`: nullable, string, unique
- `container_type`: required, string
- `max_weight`: nullable, numeric, min:0
- `max_volume`: nullable, numeric, min:0
- `facility_type`: nullable, string
- `facility_id`: nullable, integer
- `notes`: nullable, string

### Add/Remove Shipments
- `shipment_id`: required, integer, must exist in shipments table
- `shipment_ids`: required, array, min:1

### Move Shipments
- `to_container_id`: required, integer, must exist, different from source
- `shipment_ids`: required, array, min:1

## Container Statuses

- **ACTIVE**: Container is available for adding/removing shipments
- **FULL**: Container has reached max weight or volume capacity
- **IN_TRANSIT**: Container is being transported
- **DELIVERED**: Container has been delivered
- **ARCHIVED**: Container is no longer in use

## Container Types (Examples)

- BOX
- PALLET
- BAG
- CRATE
- ENVELOPE
- Custom types as needed

## Error Handling

The API returns appropriate HTTP status codes:

- `200 OK` - Successful GET, PUT, or POST operations
- `201 Created` - Successful container creation
- `206 Partial Content` - Bulk operations with some failures
- `422 Unprocessable Entity` - Validation errors or business logic failures
- `404 Not Found` - Resource not found

### Example Error Response
```json
{
    "message": "Failed to add shipment to container",
    "error": "Container has reached maximum weight capacity."
}
```

## Running Migrations

```bash
# Run the migrations to create the containers table
php artisan migrate

# Rollback migrations if needed
php artisan migrate:rollback
```

## Implementation Notes

1. **Weight Calculation**: The container uses shipment's `value` field as weight by default. Adjust in `ContainerService` if using a different field.

2. **Automatic Status Updates**: Container status automatically changes to "FULL" when capacity is reached, and back to "ACTIVE" when items are removed.

3. **Transactions**: All add/remove operations use database transactions to ensure data consistency.

4. **Soft Deletes**: Containers support soft deletes to maintain data history.

5. **Polymorphic Facilities**: Containers can belong to different facility types (Hubs, Branches, etc.) using Laravel's polymorphic relationships.

## Future Enhancements

- Container temperature/humidity monitoring
- Container dimensions tracking (length, width, height)
- Physical barcode generation and scanning
- Container movement history tracking
- Container maintenance schedules
- Integration with shipping carrier APIs
- Container cost allocation to shipments
