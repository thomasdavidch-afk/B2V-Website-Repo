import { allRoutes, websiteName } from "./allRoutes.js";
import Route from './Route.js';
import { updateNavbar } from '../js/script.js';

const routeEvent = (event) => {
    event = event || window.event;
    event.preventDefault();
    // Supporte le clic direct sur la balise <a> ou un élément enfant
    const target = event.target.closest('a');
    if (target && target.href) {
        window.history.pushState({}, "", target.href);
        LoadContentPage();
    }
};

const route404 = new Route("404", "Page introuvable", "/pages/404.html", []);

const getRouteByUrl = (url) => {
    let currentRoute = null;
    allRoutes.forEach((element) => {
        if (element.url === url) {
            currentRoute = element;
        }
    });
    return currentRoute !== null ? currentRoute : route404;
};

// Fonction qui active la couleur orange (text-action) sur le bon onglet
const updateActiveNav = () => {
    // Normalise le chemin actuel (ex: "/" si vide, retire le slash de fin pour /club/)
    let currentPath = window.location.pathname;
    if (currentPath !== "/" && currentPath.endsWith("/")) {
        currentPath = currentPath.slice(0, -1);
    }

    const navLinks = document.querySelectorAll(".navbar-nav .nav-link");

    navLinks.forEach((link) => {
        let linkPath = link.getAttribute("href");
        if (linkPath !== "/" && linkPath.endsWith("/")) {
            linkPath = linkPath.slice(0, -1);
        }

        const isCurrent = (linkPath === currentPath) || 
                          (linkPath === "/" && (currentPath === "" || currentPath === "/index.html"));

        if (isCurrent) {
            link.classList.add("active", "text-action");
            link.classList.remove("text-dark-blue");
            link.setAttribute("aria-current", "page");
        } else {
            link.classList.remove("active", "text-action");
            link.classList.add("text-dark-blue");
            link.removeAttribute("aria-current");
        }
    });
};

const LoadContentPage = async () => {
    const path = window.location.pathname;
    const actualRoute = getRouteByUrl(path);

    // Récupérer le HTML de la page
    const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
    document.getElementById("app").innerHTML = html;

    // Remonte tout en haut de la page après le chargement du contenu
    window.scrollTo(0, 0);

    // Ajouter le script JS associé à la page si présent
    if (actualRoute.pathJS && actualRoute.pathJS !== "") {
        let scriptTag = document.createElement("script");
        scriptTag.setAttribute("type", "module"); // Changé en module pour supporter les imports modernes si besoin
        scriptTag.setAttribute("src", actualRoute.pathJS);
        document.querySelector("body").appendChild(scriptTag);
    }

    // Mettre à jour le titre de l'onglet
    document.title = actualRoute.title + " - " + websiteName;

    // Met à jour la visibilité selon l'état de connexion (boutons Déconnexion, Mon Compte, etc.)
    updateNavbar();

    // Met à jour la couleur active dans la navigation
    updateActiveNav();
};

window.onpopstate = LoadContentPage;
window.route = routeEvent;
LoadContentPage();