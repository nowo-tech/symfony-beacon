# Contract: Prometheus text exposition

**Path**: `GET /metrics`  
**Content-Type**: `text/plain; version=0.0.4; charset=utf-8`

## Auth

- `401` if anonymous and token missing/invalid  
- `200` if `ROLE_ADMIN` or valid bearer/query token  

## Sample body

```text
# HELP beacon_messenger_async_pending Pending messages on the async Messenger transport
# TYPE beacon_messenger_async_pending gauge
beacon_messenger_async_pending 0
# HELP beacon_notification_destinations_failed Destinations whose last delivery failed
# TYPE beacon_notification_destinations_failed gauge
beacon_notification_destinations_failed 0
# HELP beacon_ingest_ack_total Envelope ingest requests accepted (HTTP 200)
# TYPE beacon_ingest_ack_total counter
beacon_ingest_ack_total 0
# HELP beacon_ingest_reject_total Envelope ingest requests rejected
# TYPE beacon_ingest_reject_total counter
beacon_ingest_reject_total{reason="unauthorized"} 0
```
