/*
 *   Misc JavaScipt for WineHQ Application Database
 */

// welcome
console.log('Welcome to the %cWineHQ%c AppDB',
            'color: #490708; font-size: 48pt; font-style: italic; font-weight: bold;',
            'color: #95493A; font-size: 48pt; font-style: italic; font-weight: bold;');

// execute on when document ready
$(document).ready(function()
{
    // rating hints
    (function()
    {
        var ratingdesc=[
                "",
                " Works flawlessly out of the box - no problems ",
                " Works flawlessly with DLL overrides, third party software or other settings ",
                " Works excellently for normal use;works fine in singleplayer but not multi ",
                " Works but has issues for normal use ",
                " Does not run or cannot be installed with Wine "
                ];
        var ratingstyle =[
                  "",
                  "platinum",
                  "gold",
                  "silver",
                  "bronze",
                  "garbage"
                  ];
        var changeRatingSelect = function()
        {
            var sel = $("#ratingSelect").prop('selectedIndex');
            $("#hint").attr('class', '');
            $("#hint").html(ratingdesc[sel]);
            $("#hint").addClass(ratingstyle[sel]);
        };
        $("#ratingSelect").change(function(){changeRatingSelect()});
        changeRatingSelect();
    })();

    // load nested forum comment
    $('.showComment').each(function()
    {
        $(this).click(function(e)
        {
            e.preventDefault();
            var id = $(this).data('id');
            $("#comment-"+id).html('<i class="fa fa-spinner fa-spin fa-fw"></a>');
            $.get('comment_body.php', {iCommentId: id}, function(comment)
            {
                $("#comment-"+id).html(comment);
            });
        });
    });

    // load table row by clicking anywhere on the row
    $("table").find("tr[data-donav]").each(function()
    {
        var sURL = $(this).data('donav');
        $(this).addClass('cursor-pointer');
        $(this).click(function()
        {
            document.location = sURL;
        });
    });

    // remove alert messages by clicking
    $("#whq-alert").click(function(){ $(this).fadeOut("slow"); });

    // debug log clicker
    $("div#dlogt").toggleClick(
    function()
    {
        $('div#dlogp').slideDown();
    },
    function()
    {
        $('div#dlogp').slideUp();
    });
});

/*
 * jQuery old style toggle replacement
 * this useful object was removed in jquery 1.9
 */
$.fn.toggleClick = function(){
    var functions = arguments ;
    return this.click(function(){
            var iteration = $(this).data('iteration') || 0;
            functions[iteration].apply(this, arguments);
            iteration = (iteration + 1) % functions.length ;
            $(this).data('iteration', iteration);
    });
};

// open Window (FIXME: replace with an inline screenshot viewer)
function openWin(fileToOpen,nameOfWindow,width,height) {
    myWindow = window.open("",nameOfWindow,"menubar=no,scrollbars=yes,status=no,width="+width+",height="+height);
    myWindow.document.open();
    myWindow.document.write('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">');
    myWindow.document.write('<html><head><title>Screenshot Viewer</title>')
    myWindow.document.write('<style type="text/css">');
    myWindow.document.write('body { margin: 0; padding: 0; background-color: lightgrey; }');
    myWindow.document.write('img { border: 0; }');
    myWindow.document.write('p { display: inline; }');
    myWindow.document.write('</style></head><body>');
    myWindow.document.write('<a onclick="self.close();" href=""><img src="'+ fileToOpen +'" alt="Screenshot"></a>');
    myWindow.document.write('</body></html>');
    myWindow.document.close();
}

// done