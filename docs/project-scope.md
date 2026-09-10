# Property Defect Resolution Portal — Project Scope

## Purpose
Help a small construction or renovation company report property
defects, assign repairs, and verify that repairs are completed.

## First Release
A hosted, mobile-friendly web application for one company
managing multiple properties.

Target: a first release for a small client pilot within one month.

## User Roles

### Manager
- Manage properties and rooms.
- Invite users and control property access.
- Report and review defects.
- Assign contractors, priorities, and deadlines.
- Verify repairs or reopen defects.
- View dashboards and generate handover reports.

### Contractor
- View assigned defects.
- Record progress and add comments.
- Upload repair photos and completion notes.
- Mark defects as repaired.

### Property Owner
- Access only their own properties.
- Report defects and upload photos.
- View repair progress and add comments.
- Request another review through comments.

## Defect Workflow
Reported → Assigned → In progress → Repaired → Verified

The manager can reopen repaired or verified defects.
Reopened defects return to In progress when work resumes.

Repaired means the contractor has submitted the completed work.
Verified means the manager has checked and accepted the repair.

## Essential Features
- Login, invitations, and password reset.
- Role-based and property-specific access.
- Property and room management.
- Defect reporting with descriptions and photos.
- One assigned contractor per defect.
- Priorities and deadlines.
- Repair submission, verification, and reopening.
- Comments and activity history.
- Search and filtering.
- Basic dashboards.
- Printable property handover reports.

## Key Rules
- Owners can access only their own properties.
- Contractors can access only defects assigned to them.
- Only managers can assign contractors and verify repairs.
- Repair submission requires a completion note and photo.
- Reopening requires an explanation.
- Assignment, deadline, and status changes are recorded.
- Uploaded photos require authorised access.
- Deactivating a user preserves their historical records.

## Outside the First Release
- Payments and subscriptions.
- Live chat.
- AI photo analysis.
- Offline synchronisation.
- Interactive floor-plan markers.
- Native mobile applications.
- Multiple independent companies sharing the application.

## Planned Technology
- Backend: PHP and Laravel.
- Frontend: Blade, Livewire, and Tailwind CSS.
- Database: MySQL.
- Version control: Git and GitHub.
- Deployment: Laravel-compatible hosting with HTTPS,
  persistent private photo storage, and email delivery.

## Acceptance Criteria
- An owner can report a defect for their property.
- A manager can assign it to a contractor.
- The contractor can submit repair evidence.
- The manager can verify or reopen the repair.
- Unauthorised property and defect access is denied.
- Reports distinguish verified and unresolved defects.
- The main workflow works on mobile.
- Backup restoration is checked before the client pilot.

## Validation Status
This scope is agreed as the initial development scope.
The workflow still needs validation with a potential client.