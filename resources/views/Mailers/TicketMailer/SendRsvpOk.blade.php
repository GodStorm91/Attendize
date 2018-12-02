@extends('en.Emails.Layouts.Master')

@section('message_content')

Thân chào bạn, <br>
<br>
Cảm ơn bạn đã đăng ký tham gia chương trình Viet Tech Day Tokyo 2018 do VPJ tổ chức. <br><br>

Như đã thông báo, do quy mô hội trường có hạn trong khi số lượng đăng ký quá lớn, BTC đã tiến hành bốc thăm để chọn người tham gia. <br><br>

<b>Chúc mừng bạn đã may mắn được chọn! </b><br>
<br>
Để đảm bảo chính xác số lượng người, mời bạn <b>xác nhận tham gia (hoặc huỷ vé)</b> theo link dưới đây. Cần xác nhận trước 23:59 ngày 3 tháng 12, 2018 (thứ Hai).
<br>
    <p align="center"><a href="{{url('/')}}/sendRsvp?o={{$attendee->order->order_reference}}&s=1" style="margin-right:20px;background:#11356D;color:white;display:inline-block;width:auto; text-align:center;-webkit-border-radius: 10px;-moz-border-radius: 10px;border-radius: 10px;text-decoration:none;border:10px solid #11356D;-moz-box-shadow: 3px 0 0 0 #ccc; -webkit-box-shadow: 3px 0 0 0 #ccc; box-shadow: 3px 0 0 0 #ccc; ">Xác Nhận</a>         <a href="{{url('/')}}/sendRsvp?o={{$attendee->order->order_reference}}&s=4" style="background:#ff6666;color:white;display:inline-block;width:auto; text-align:center;-webkit-border-radius: 10px;-moz-border-radius: 10px;border-radius: 10px;text-decoration:none;border:10px solid #ff6666;-moz-box-shadow: 3px 0 0 0 #ccc; -webkit-box-shadow: 3px 0 0 0 #ccc; box-shadow: 3px 0 0 0 #ccc; ">Huỷ Đăng Ký</a></p>

<br>

Và đừng quên theo dõi trang Facebook của VPJ để có thêm thông tin về chương trình!<br>

<br>
Thân mến, <br>
Viet Tech Day Tokyo 2018 Organizing team

<br><br>
@stop
