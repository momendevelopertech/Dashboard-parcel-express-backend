# Container Module - Setup Guide

## Files Created

This container module includes the following files:

### 1. **Migrations**
- `database/migrations/2026_02_06_000001_create_containers_table.php` - Creates the containers table
- `database/migrations/2026_02_06_000002_add_container_id_to_shipments_table.php` - Adds container_id to shipments table

### 2. **Models**
- `app/Models/Container.php` - Container model with relationships and helper methods

### 3. **Services**
- `app/Services/ContainerService.php` - Business logic for container management

### 4. **Controllers**
- `app/Http/Controllers/Api/V1/ContainerController.php` - API endpoints

### 5. **Routes**
- Added container routes to `routes/api_v1.php`

### 6. **Tests**
- `tests/Feature/ContainerTest.php` - Comprehensive test suite

### 7. **Documentation**
- `CONTAINER_MODULE.md` - Full API and implementation documentation
- `CONTAINER_SETUP.md` - This file

## Setup Instructions

### Step 1: Run Migrations

```bash
php artisan migrate
```

This will:
- Create the `containers` table
- Add `container_id` foreign key to the `shipments` table

### Step 2: Verify Model Relationships

The Shipment model has been updated with:
```php
public function container()
{
    return $this->belongsTo(Container::class, 'container_id');
}
```

### Step 3: Test the Module

Run the test suite:
```bash
php artisan test tests/Feature/ContainerTest.php
```

### Step 4: Clear Cache (if needed)

If you encounter any issues with route or model caching:
```bash
php artisan cache:clear
php artisan route:cache
```

## Quick Start - Using the API

### Create a Container
```bash
curl -X POST http://localhost/api/v1/containers \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "container_type": "BOX",
    "max_weight": 100,
    "max_volume": 50,
    "notes": "Shipping container"
  }'
```

### Add Shipment to Container
```bash
curl -X POST http://localhost/api/v1/containers/1/add-shipment \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"shipment_id": 42}'
```

### Get Container Details
```bash
curl -X GET http://localhost/api/v1/containers/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Add Multiple Shipments
```bash
curl -X POST http://localhost/api/v1/containers/1/add-multiple-shipments \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"shipment_ids": [42, 43, 44]}'
```

## Quick Start - Using the Service Class

```php
<?php

use App\Services\ContainerService;
use App\Models\Container;

$containerService = app(ContainerService::class);

// Create container
$container = $containerService->createContainer([
    'container_type' => 'BOX',
    'max_weight' => 100,
    'created_by' => auth()->id(),
]);

// Add shipments
$containerService->addShipment($container, $shipment);

// Check capacity
if ($container->canAcceptWeight(25)) {
    echo "Container can accept 25 units";
}

// Get details
$details = $containerService->getContainerDetails($container);
echo "Utilization: " . $details['utilization_percentage'] . "%";
```

## Container Statuses

- **ACTIVE** - Ready to add/remove shipments
- **FULL** - At maximum capacity
- **IN_TRANSIT** - Being transported
- **DELIVERED** - Shipment completed
- **ARCHIVED** - No longer in use

## Key Features

✅ Create and manage containers
✅ Add/remove shipments from containers
✅ Bulk operations (add/remove multiple shipments)
✅ Move shipments between containers
✅ Automatic capacity tracking
✅ Automatic status management
✅ Weight and volume utilization monitoring
✅ Container association with facilities (Hubs, Branches, etc.)
✅ User tracking (who created the container)
✅ Soft deletes for data preservation
✅ Audit trail with timestamps

## Authorization

Make sure to set up permissions for:
- `Container access` - View containers
- `Container create` - Create containers
- `Container update` - Update containers and manage shipments
- `Container delete` - Delete containers

Example in your authorization:
```php
'Container' => [
    'access' => 'access',
    'create' => 'create',
    'update' => 'update',
    'delete' => 'delete',
]
```

## Database Schema

### Containers Table
| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PRIMARY KEY | |
| container_number | VARCHAR(255) UNIQUE | Auto-generated if not provided |
| tracking_no | VARCHAR(255) UNIQUE | Auto-generated tracking number |
| container_type | VARCHAR(255) | BOX, PALLET, CRATE, etc. |
| max_weight | DECIMAL(10,2) | Nullable |
| current_weight | DECIMAL(10,2) | Default 0 |
| max_volume | DECIMAL(10,2) | Nullable |
| current_volume | DECIMAL(10,2) | Default 0 |
| shipment_count | INT | Tracks number of shipments |
| status | VARCHAR(255) | ACTIVE, FULL, IN_TRANSIT, DELIVERED, ARCHIVED |
| facility_type | VARCHAR(255) | Polymorphic relationship |
| facility_id | BIGINT | Polymorphic relationship |
| created_by | BIGINT FK | User who created container |
| notes | TEXT | Additional notes |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP (NULL) | Soft delete support |

### Shipments Table Changes
Added column:
- `container_id` BIGINT FOREIGN KEY (containers, id) - NULL on delete

## Troubleshooting

### Migration Fails
- Ensure `shipments` table exists before running migrations
- Check that asset_id column exists in shipments table for the second migration

### Container routes not working
- Clear route cache: `php artisan route:cache`
- Verify the ContainerController import in `routes/api_v1.php`

### Authorization errors
- Make sure your auth middleware is properly configured
- Verify that authorization gates are set up for Container resource

### Tests failing
- Run with verbose: `php artisan test tests/Feature/ContainerTest.php -v`
- Check that the test database is properly configured

## Next Steps

1. **Run migrations** to set up the database
2. **Run tests** to verify everything works
3. **Review API documentation** in CONTAINER_MODULE.md
4. **Integrate into your application** as needed
5. **Customize** container types and statuses based on your requirements

## Support

For detailed API documentation, see: `CONTAINER_MODULE.md`

For implementation examples, see: `tests/Feature/ContainerTest.php`

## Features Coming Soon

- [ ] Container barcode generation and scanning
- [ ] Temperature/humidity monitoring
- [ ] Container dimensions tracking
- [ ] Movement history and tracking
- [ ] Maintenance schedules
- [ ] Cost allocation to shipments
- [ ] Carrier API integration
