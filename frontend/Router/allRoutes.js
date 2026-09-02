import Route from "./Route.js";

// Définir ici vos routes
export const allRoutes = [
    new Route("/", "Accueil", "/pages/home.html", []),
    new Route("/contact", "Contact", "/pages/contact.html", []),
    new Route("/signin", "Connexion", "/pages/auth/signin.html", ["disconnected"]),
    new Route("/register", "Inscription", "/pages/auth/register.html", ["disconnected"]),
    new Route("/mentions-legales", "Mentions légales", "/pages/mentions-legales.html", []),
    new Route("/reglement-interieur", "Règlement Intérieur", "/pages/reglement-interieur.html", []),
    new Route("/cgv", "CGV", "/pages/cgv.html", []),
    new Route("/faq", "FAQ", "/pages/faq.html", []),
    new Route("/club", "Le Club", "/pages/club.html", []),
    new Route("/accountUser", "Mon Compte", "/pages/auth/accountUser.html", []),
];

// Le titre affiché sur l'onglet
export const websiteName = "B2V - Beach Volley Vibes";