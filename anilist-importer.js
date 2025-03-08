jQuery(document).ready(function ($) {
    $('#fetch_anilist_btn').click(function (e) {
        e.preventDefault();
        
        let title = $('#anime_title').val();
        if (!title) {
            alert('Inserisci un titolo');
            return;
        }

        $.post(AnilistImporter.ajax_url, {
            action: 'fetch_anilist_data',
            title: title
        }, function (response) {
            if (response.success) {
                let anime = response.data;
                $('#anime_episodes').val(anime.episodes);
                $('#anime_poster').val(anime.poster);
                $('#anime_background').val(anime.background);
                $('#anime_studio').val(anime.studio);
                $('#anime_year').val(anime.year);
                $('#anime_season').val(anime.season);
            } else {
                alert('Errore: ' + response.data.message);
            }
        });
    });
});
