@extends('en.Emails.Layouts.Master')

@section('message_content')
Xin chào,{{$order->full_name}}<br><br>

Việc đăng kí của bạn cho sự kiện <b>{{$order->event->title}}</b> đã thành công.<br><br>

@if ($order->event->id == 7)
Đây là mã số đăng kí của bạn. Xin lưu ý là việc đăng kí chưa hoàn tất.
<br>
Trong trường hợp số lượng người đăng kí quá đông, ban tổ chức sẽ tổ chức chọn ngẫu nhiên trong danh sách những người đăng kí.
Thư xác nhận đăng ký sẽ được gửi vào ngày 5 tháng 12 năm 2018.
<br>
Bên dưới là thông tin đăng kí của bạn:
{{route('showOrderDetails', ['order_reference' => $order->order_reference])}}
@endif

<h3>Thông tin chi tiết</h3>
Mã đặt chỗ: <b>{{$order->order_reference}}</b><br>
Tên : <b>{{$order->full_name}}</b><br>
Ngày đặt chỗ: <b>{{$order->created_at->toDayDateTimeString()}}</b><br>
Email: <b>{{$order->email}}</b><br>

<h3>Thông tin đặt chỗ</h3>
<div style="padding:10px; background: #F9F9F9; border: 1px solid #f1f1f1;">
    <table style="width:100%; margin:10px;">
        <tr>
            <td>
                <b>Loại vé</b>
            </td>
            <td>
                <b>Số lượng</b>
            </td>
            <td>
                <b>Giá</b>
            </td>
            <td>
                <b>Phụ phí</b>
            </td>
            <td>
                <b>Tổng cộng</b>
            </td>
        </tr>
        @foreach($order->orderItems as $order_item)
                                <tr>
                                    <td>
                                        {{$order_item->title}}
                                    </td>
                                    <td>
                                        {{$order_item->quantity}}
                                    </td>
                                    <td>
                                        @if((int)ceil($order_item->unit_price) == 0)
                                        FREE
                                        @else
                                       {{money($order_item->unit_price, $order->event->currency)}}
                                        @endif

                                    </td>
                                    <td>
                                        @if((int)ceil($order_item->unit_price) == 0)
                                        -
                                        @else
                                        {{money($order_item->unit_booking_fee, $order->event->currency)}}
                                        @endif

                                    </td>
                                    <td>
                                        @if((int)ceil($order_item->unit_price) == 0)
                                        FREE
                                        @else
                                        {{money(($order_item->unit_price + $order_item->unit_booking_fee) * ($order_item->quantity), $order->event->currency)}}
                                        @endif

                                    </td>
                                </tr>
                                @endforeach
        <tr>
            <td>
            </td>
            <td>
            </td>
            <td>
            </td>
            <td>
                <b>Sub Total</b>
            </td>
            <td colspan="2">
                {{$orderService->getOrderTotalWithBookingFee(true)}}
            </td>
        </tr>
        @if($order->event->organiser->charge_tax == 1)
        <tr>
            <td>
            </td>
            <td>
            </td>
            <td>
            </td>
            <td>
                <b>{{$order->event->organiser->tax_name}}</b>
            </td>
            <td colspan="2">
                {{$orderService->getTaxAmount(true)}}
            </td>
        </tr>
        @endif
        <tr>
            <td>
            </td>
            <td>
            </td>
            <td>
            </td>
            <td>
                <b>Total</b>
            </td>
            <td colspan="2">
                {{$orderService->getGrandTotal(true)}}
            </td>
        </tr>
    </table>

    <br><br>
</div>
<br><br>
Cám ơn bạn đã quan tâm đến VPJ
@stop