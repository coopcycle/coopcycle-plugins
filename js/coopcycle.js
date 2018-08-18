var moment = rome.moment;

// Nous livrons deux fois par semaine, en fin d’après-midi et soirée pour que vous puissiez être présents lors de la livraison.
// Le mardi soir de 18 à 20 h 30 > commande jusqu’au lundi minuit
// Le vendredi soir de 18 à 20 h 30 > commande jusqu’au mercredi minuit
// Votre commande doit être passée avant le lundi minuit pour la livraison du mardi soir.
// Votre commande doit être passée avant le mercredi minuit pour la livraison du vendredi soir.

var el = document.querySelector('#order_shipping_time');

if (el) {

    var opens = moment(document.querySelector('#order_shipping_date_opens').getAttribute('datetime'));
    var closes = moment(document.querySelector('#order_shipping_date_closes').getAttribute('datetime'));

    rome(el, {
        date: false,
        initialValue: opens,
        // Seconds between each option in the time dropdown
        timeInterval: (15 * 60),
        timeValidator: function(d) {

            var m = moment(d);

            var start = m.clone()
                .hour(opens.hour())
                .minute(opens.minute())
                .second(opens.second());
            var end = m.clone()
                .hour(closes.hour())
                .minute(closes.minute())
                .second(closes.second());

            return (m.isSame(start) || m.isAfter(start)) && (m.isSame(end) || m.isBefore(end));
        }
    });
}
