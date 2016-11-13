function passwordWarning(text, score) {
    var html = '';
    switch (text) {
        case 'Common names and surnames are easy to guess':
            html = "The password you chose appears to be similar to a common name or surname.";
            break;
        case 'This is a top-10 common password':
            html = "The password you chose is one of the 10 most common passwords that other people choose.";
            break;
        case 'This is a top-100 common password':
            html = "The password you chose is one of the 100 most common passwords that other people choose.";
            break;
        case 'This is a very common password':
            html = "The password you chose is very common.";
            break;
        case 'This is similar to a commonly used password':
            html = "The password you chose is similar to a commonly used password.";
            break;
    }

    if (score === 4) {
        $("#password_feedback")
            .removeClass('passwordBad')
            .removeClass('passwordAcceptable')
            .addClass('passwordGood');
        html = 'Good password, as long as it\'s unique!<br />If you\'re not already, consider using a password manager such as <a target="_blank" rel="noopener noreferrer" href="https://github.com/keepassx/keepassx/">KeePassX</a>.';
    } else if (score === 3) {
        $("#password_feedback")
            .removeClass('passwordBad')
            .addClass('passwordAcceptable')
            .removeClass('passwordGood');
         html += "<br />If you use this password, it might be guessable by criminals.";
    } else if (score === 2) {
        $("#password_feedback")
            .addClass('passwordBad')
            .removeClass('passwordAcceptable')
            .removeClass('passwordGood');
         html += "<br />If you use this password, it will be easily guessable by criminals.";
    } else {
        $("#password_feedback")
            .addClass('passwordBad')
            .removeClass('passwordAcceptable')
            .removeClass('passwordGood');
         html += "<br />If you use this password, it will be <em>very</em> easily guessable by criminals.";
    }
    $("#password_feedback").html(html);
}