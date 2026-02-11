@component('mail::message')
# Parcel Storage Duration Alert

A parcel has been waiting in your facility beyond the allowed period:

**Tracking Number:** {{ $trackingNo }}  
**Shelf Location:** {{ $shelfBarcode }}  
**Facility:** {{ $facilityName }}  
**Days in Storage:** {{ $daysWaited }} days

Please take appropriate action to move this parcel.

Thank you,  
{{ config('app.name') }}
@endcomponent
