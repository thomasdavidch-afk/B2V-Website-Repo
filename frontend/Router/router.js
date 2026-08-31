import { allRoutes, websiteName } from "./allRoutes.js";
import Route from './Route.js'; // Ajustez le chemin selon l'emplacement de votre fichier Route.js

const routeEvent = (event) => {
    event = event || window.event;
    event.preventDefault();
    window.history.pushState({}, "", event.target.href);
    LoadContentPage();
};

const route404 = new Route("404", "Page introuvable", "/pages/404.html", []);

const getRouteByUrl = (url) => {
    let currentRoute = null;
    allRoutes.forEach((element) => {
        if (element.url === url) {
            currentRoute = element;
        }
    });
    if (currentRoute != null) {
        return currentRoute;
    } else {
        return route404;
    }
};

const LoadContentPage = async () => {
    const path = window.location.pathname;
    const actualRoute = getRouteByUrl(path);

    // Récupérer le HTML de la page
    const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
    document.getElementById("app").innerHTML = html;

    // Ajouter le script JS associé à la page si présent
    if (actualRoute.pathJS !== "") {
        let scriptTag = document.createElement("script");
        scriptTag.setAttribute("type", "text/javascript");
        scriptTag.setAttribute("src", actualRoute.pathJS);
        document.querySelector("body").appendChild(scriptTag);
    }

    // Mettre à jour le titre de l'onglet
    document.title = actualRoute.title + " - " + websiteName;
};

window.onpopstate = LoadContentPage;
window.route = routeEvent;
LoadContentPage();