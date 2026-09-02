<script setup>
import { computed } from 'vue';

const previousState = useCtPreviousState();
const count = computed(() => previousState.count.value + 1);

ctDefineOverride({
    count,
});
</script>
