import Route from "./Route.js";

// Définir ici vos routes
export const allRoutes = [
    new Route("/", "Accueil", "/pages/home.html", []),
    new Route("/contact", "Contact", "/pages/contact.html", []),
    new Route("/signin", "Connexion", "/pages/auth/signin.html", ["disconnected"]),
    new Route("/signup", "Inscription", "/pages/auth/signup.html", ["disconnected"]),
];

// Le titre affiché sur l'onglet
export const websiteName = "B2V - Beach Volley Vibes";