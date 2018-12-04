<?php

namespace App\Mailers;

use App\Models\Attendee;
use App\Models\Message;
use Carbon\Carbon;
use Log;
use Mail;


class AttendeeMailer extends Mailer
{

    public function sendAttendeeTicket($attendee)
    {

        Log::info("Sending ticket to: " . $attendee->email);

        $data = [
            'attendee' => $attendee,
        ];

        Mail::send('Mailers.TicketMailer.SendAttendeeTicket', $data, function ($message) use ($attendee) {
            $message->to($attendee->email);
            $message->subject(trans("Email.your_ticket_for_event", ["event" => $attendee->order->event->title]));

            $file_name = $attendee->reference;
            $file_path = public_path(config('attendize.event_pdf_tickets_path')) . '/' . $file_name . '.pdf';

            $message->attach($file_path);
        });

    }

    /**
     * Sends the attendees a message
     *
     * @param Message $message_object
     */
    public function sendMessageToAttendees(Message $message_object)
    {
        $event = $message_object->event;

        $attendees = ($message_object->recipients == 'all')
            ? $event->attendees // all attendees
            : Attendee::where('ticket_id', '=', $message_object->recipients)->where('account_id', '=',
                $message_object->account_id)->get();

        foreach ($attendees as $attendee) {

            $data = [
                'attendee'        => $attendee,
                'event'           => $event,
                'message_content' => $message_object->message,
                'subject'         => $message_object->subject,
                'email_logo'      => $attendee->event->organiser->full_logo_path,
            ];

            Mail::send('Emails.messageReceived', $data, function ($message) use ($attendee, $data) {
                $message->to($attendee->email, $attendee->full_name)
                    ->from(config('attendize.outgoing_email_noreply'), $attendee->event->organiser->name)
                    ->replyTo($attendee->event->organiser->email, $attendee->event->organiser->name)
                    ->subject($data['subject']);
            });
        }

        $message_object->is_sent = 1;
        $message_object->sent_at = Carbon::now();
        $message_object->save();
    }

    public function SendAttendeeInvite($attendee)
    {

        Log::info("Sending invite to: " . $attendee->email);

        $data = [
            'attendee' => $attendee,
        ];

        Mail::send('Mailers.TicketMailer.SendAttendeeInvite', $data, function ($message) use ($attendee) {
            $message->to($attendee->email);
            $message->subject(trans("Email.your_ticket_for_event", ["event" => $attendee->order->event->title]));

            $file_name = $attendee->getReferenceAttribute();
            $file_path = public_path(config('attendize.event_pdf_tickets_path')) . '/' . $file_name . '.pdf';

            $message->attach($file_path);
        });

    }


    public function sendRsvpOk($attendee)
    {

        Log::info("Sending invite to: " . $attendee->email);

        $data = [
            'attendee' => $attendee,
        ];

        Mail::send('Mailers.TicketMailer.SendRsvpOk', $data, function ($message) use ($attendee) {
            $message->to($attendee->email);
            $message->subject("[VPJ] Xác nhận đăng ký thành công Viet Tech Day Tokyo 2018");

            $file_name = $attendee->getReferenceAttribute();
            $file_path = public_path(config('attendize.event_pdf_tickets_path')) . '/' . $file_name . '.pdf';
            $message->attach($file_path);
        });

    }

    public function sendRsvpNg($attendee)
    {

        Log::info("Sending invite to: " . $attendee->email);

        $data = [
            'attendee' => $attendee,
        ];

        Mail::send('Mailers.TicketMailer.SendRsvpNg', $data, function ($message) use ($attendee) {
            $message->to($attendee->email);
            $message->subject("[VPJ] Thông báo từ Viet Tech Day Tokyo 2018");
        });

    }

    public function sendRemind($attendee)
    {

        Log::info("Sending remind to: " . $attendee->email);

        $data = [
            'attendee' => $attendee,
        ];

        Mail::send('Mailers.TicketMailer.SendRemind', $data, function ($message) use ($attendee) {
            $message->to($attendee->email);
            $message->subject("[VPJ] Hạn xác nhận đăng ký Viet Tech Day Tokyo 2018 sắp hết");
        });

    }

}
