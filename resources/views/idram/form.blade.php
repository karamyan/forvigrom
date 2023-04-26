<form name="submit-idram-deposit-{{ $data['EDP_BILL_NO'] }}" id="{{ $data['EDP_BILL_NO'] }}" style="display:none" action="{{ $actionUrl }}" method="POST">
    @foreach($data as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <input type="submit" value="submit">
</form>
<script>
    document.getElementById("{{ $data['EDP_BILL_NO'] }}").submit();
</script>
