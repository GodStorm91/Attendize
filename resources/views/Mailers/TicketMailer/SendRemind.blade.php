@extends('en.Emails.Layouts.Master')

@section('message_content')

Cảm ơn bạn đã đăng ký tham gia chương trình Viet Tech Day Tokyo 2018 do VPJ tổ chức.
<br>
<br>
Như đã thông báo, do quy mô hội trường có hạn trong khi số lượng đăng ký quá lớn, BTC đã tiến hành bốc thăm để chọn người tham gia. Chúc mừng bạn đã may mắn được chọn!
<br>
Bạn cần xác nhận tham gia (hoặc huỷ vé) theo link dưới đây:
<br>
<br>
    <p align="center"><a href="{{url('/')}}/sendRsvp?o={{$attendee->order->order_reference}}&s=1" style="margin-right:20px;background:#11356D;color:white;display:inline-block;width:auto; text-align:center;-webkit-border-radius: 10px;-moz-border-radius: 10px;border-radius: 10px;text-decoration:none;border:10px solid #11356D;-moz-box-shadow: 3px 0 0 0 #ccc; -webkit-box-shadow: 3px 0 0 0 #ccc; box-shadow: 3px 0 0 0 #ccc; ">Xác Nhận</a>         <a href="{{url('/')}}/sendRsvp?o={{$attendee->order->order_reference}}&s=4" style="background:#ff6666;color:white;display:inline-block;width:auto; text-align:center;-webkit-border-radius: 10px;-moz-border-radius: 10px;border-radius: 10px;text-decoration:none;border:10px solid #ff6666;-moz-box-shadow: 3px 0 0 0 #ccc; -webkit-box-shadow: 3px 0 0 0 #ccc; box-shadow: 3px 0 0 0 #ccc; ">Huỷ Đăng Ký</a></p>

<br>
<br>
LƯU Ý: Do quy định hội trường nên BTC cần phải in vé giấy cho người tham gia. Hạn xác nhận là <font color="red"><b>trước 23:59 ngày 4 tháng 12, 2018 (thứ Ba).</font></b> Nếu không vé sẽ <b>TỰ ĐỘNG BỊ HUỶ. </b>    
<br>

<br>
Cảm ơn bạn và mong nhận được phản hồi sớm từ bạn!
<br>
<br>
Thân mến,
<br>
Viet Tech Day Tokyo 2018 Organizing team
<br><br>
@stop