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
    let currentPath = window.location.pathname;
    if (currentPath !== "/" && currentPath.endsWith("/")) {
        currentPath = currentPath.slice(0, -1);
    }

    const navLinks = document.querySelectorAll(".navbar-nav .nav-link");

    navLinks.forEach((link) => {
        let linkPath = link.getAttribute("href");
        if (linkPath && linkPath !== "/" && linkPath.endsWith("/")) {
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

    try {
        // 1. Récupérer et injecter le HTML de la page
        const html = await fetch(actualRoute.pathHtml).then((data) => data.text());
        document.getElementById("app").innerHTML = html;

        // 2. Remonter tout en haut de la page
        window.scrollTo(0, 0);

        // 3. Charger et ré-exécuter le JS associé à la page
        if (actualRoute.pathJS && actualRoute.pathJS !== "") {
            // Nettoyage de l'ancien script de page s'il existait
            const oldScript = document.getElementById("page-custom-script");
            if (oldScript) {
                oldScript.remove();
            }

            // Création du nouveau script (sans type="module" pour ré-exécution garantie)
            const scriptTag = document.createElement("script");
            scriptTag.id = "page-custom-script";
            scriptTag.src = `${actualRoute.pathJS}?v=${Date.now()}`; // Forcer le rechargement
            document.body.appendChild(scriptTag);
        }

        // 4. Ré-initialisation explicite si les fonctions globales existent déjà
        if (path === '/accountUser' && typeof window.initAccountUser === 'function') {
            window.initAccountUser();
        } else if (path === '/accountAdmin' && typeof window.initAccountAdmin === 'function') {
            window.initAccountAdmin();
        }

        // 5. Mettre à jour le titre
        document.title = actualRoute.title + " - " + websiteName;

        // 6. Mettre à jour l'état de la Navbar et les liens actifs
        updateNavbar();
        updateActiveNav();

    } catch (error) {
        console.error("Erreur de routage :", error);
    }
};

window.onpopstate = LoadContentPage;
window.route = routeEvent;
window.LoadContentPage = LoadContentPage;
LoadContentPage();