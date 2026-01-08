<form method="POST" action="send_notification.php">
    Student ID (fyp_studid):<br>
    <input type="number" name="stud_id" required><br><br>

    Subject:<br>
    <input type="text" name="subject" required><br><br>

    Message:<br>
    <textarea name="message" rows="5" required></textarea><br><br>

    <button type="submit">Send Email</button>
</form>