<template>
    <footer class="bg-gray-800 text-gray-300 mt-12">
        <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- À propos -->
                <div>
                    <h3 class="text-white font-semibold mb-4">À propos</h3>
                    <p class="text-sm">
                        Calculateur de rentabilité pour Dofus. Analysez les coûts de craft et maximisez vos profits.
                    </p>
                </div>
                
                <!-- Données & Sources -->
                <div>
                    <h3 class="text-white font-semibold mb-4">Données & Sources</h3>
                    <p class="text-sm mb-2">
                        Les données des items et recettes proviennent de 
                        <a href="https://dofusdb.fr" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">
                            DofusDB
                        </a>
                    </p>
                    <p class="text-sm">
                        Les prix sont fournis par la communauté.
                    </p>
                </div>
                
                <!-- Mentions légales -->
                <div>
                    <h3 class="text-white font-semibold mb-4">Mentions légales</h3>
                    <p class="text-sm mb-2">
                        Les images et contenus graphiques sont la propriété d'
                        <a href="https://www.ankama.com" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">
                            Ankama Games
                        </a>
                    </p>
                    <p class="text-sm">
                        Dofus est un MMORPG édité par Ankama. Ce site n'est pas affilié à Ankama.
                    </p>
                </div>
            </div>
            
            <div class="mt-8 pt-8 border-t border-gray-700 text-center">
                <p class="text-sm">
                    © {{ new Date().getFullYear() }} Dofus Calculator - 
                    Données fournies par <a href="https://dofusdb.fr" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">DofusDB</a> - 
                    Images © <a href="https://www.ankama.com" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300">Ankama Games</a>
                </p>
            </div>
        </div>
    </footer>
</template>

<script setup>
// Footer component with legal mentions
</script>