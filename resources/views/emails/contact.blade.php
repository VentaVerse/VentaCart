<x-mail::message>
# New Contact Message

**From:** {{ $data['name'] }} ({{ $data['email'] }})
**Subject:** {{ $data['subject'] }}

---

{{ $data['message'] }}

---

*This message was sent via the contact form on {{ config('app.name') }}.*
</x-mail::message>
