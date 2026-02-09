function showTradeTab(type) {
    const ongoingTab = document.getElementById('ongoingTab');
    const completedTab = document.getElementById('completedTab');
    const allTab = document.getElementById('allTab');

    const btnOngoing = document.getElementById('btnOngoing');
    const btnCompleted = document.getElementById('btnCompleted');
    const btnAll = document.getElementById('btnAll');

    ongoingTab.style.display = type === 'ongoing' ? 'block' : 'none';
    completedTab.style.display = type === 'completed' ? 'block' : 'none';
    allTab.style.display = type === 'all' ? 'block' : 'none';

    btnOngoing.classList.toggle('active', type === 'ongoing');
    btnCompleted.classList.toggle('active', type === 'completed');
    btnAll.classList.toggle('active', type === 'all');
}
