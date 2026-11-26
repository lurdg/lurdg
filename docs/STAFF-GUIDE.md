# Staff operations

## Access

The super administrator manages all roles and can permanently purge a trashed shipment. An administrator can manage shipments, settings, statuses, the inbox and subordinate staff. Shipment managers manage shipments, customer profiles and enquiries. Operations officers can record status and location updates. Delivery agents can access active shipments assigned to them and record delivery statuses or locations.

Create staff accounts individually and give each person the minimum role necessary. Initial staff passwords are entered in the protected dashboard and should be conveyed securely. A password or role change revokes existing sessions for that account. Password resets use short-lived single-use links when SMTP is configured. An eligible administrator can update a subordinate account. The first owner is created during setup.

## Shipments

1. Open **Shipments → Create shipment**. Enter the origin, destination, party names and shipping method. Fill in the schedule, parcel details and other fields as available.
2. Choose a status and assign an agent when appropriate. Saving generates a tracking number and an initial event.
3. Share the tracking number with the intended customer. Anyone with the number can see the sender and receiver names and addresses saved on the shipment.
4. Keep internal and customer-visible notes separate. A public location label and customer-visible remarks are displayed with the timeline. Material intended for staff belongs in the internal description.
5. On the shipment details page, enter a place name under **Record location** and click **Generate coordinates**. One match fills the fields automatically. If several appear, select the correct place. Review the location and coordinates before saving. You can also enter coordinates directly. Use the actual event time if known. A backdated status adds history without changing the current status when a newer event exists.
6. Use **Shipments** filters for status, origin, destination and creation date. Search by tracking number or exact reference. **Export filtered CSV** includes private fields and must be handled accordingly. Select **Print details** or **Shipping label** for a print-ready page.
7. Archive older shipments to remove them from the active list and retain public tracking. Unarchive them to resume editing. Move a shipment to Trash to take its tracking result offline. Restore it from the Trash filter. Only a super administrator can permanently delete a trashed shipment.
8. Upload up to 8 MB per PDF, JPEG or PNG. Documents are accessible to eligible staff through the shipment record. A status in use can be retired so that old history retains its meaning.

## Inbox, customers and audit

Quotes and contact messages arrive in **Inbox**. Open an item to update its follow-up stage or notes. Customer profiles are internal and can be linked to a shipment. The shipment keeps its own sender and receiver information if the profile is edited or removed. **Audit log** records staff sign-ins, role changes, record changes, history corrections and deletion actions with time and actor.

## Database and files

The application displays the configured timezone and stores UTC timestamps. The location on a shipment is a record created by staff. It updates when a new coordinate event is recorded. Keep exports, label printouts, backups and documents in controlled storage.
